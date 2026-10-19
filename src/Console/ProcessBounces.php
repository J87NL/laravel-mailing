<?php

declare(strict_types=1);

namespace J87NL\Mailing\Console;

use Illuminate\Console\Command;
use J87NL\Mailing\Actions\ApplyBounce;
use J87NL\Mailing\Actions\ApplyDeliveryEvent;
use J87NL\Mailing\Bounces\DsnParser;
use J87NL\Mailing\Contracts\MailboxReader;
use J87NL\Mailing\Delivery\DeliveryEvent;
use J87NL\Mailing\Enums\BounceType;
use J87NL\Mailing\Enums\DeliveryEventType;
use J87NL\Mailing\Models\Send;

class ProcessBounces extends Command
{
    protected $signature = 'mailing:process-bounces';

    protected $description = 'Read the bounce mailbox and apply the delivery status notifications';

    public function handle(MailboxReader $reader, DsnParser $parser, ApplyBounce $applyBounce, ApplyDeliveryEvent $applyDeliveryEvent): int
    {
        $processed = 0;

        foreach ($reader->unprocessed() as $message) {
            $report = $parser->parse($message->raw);

            if (! is_null($report)) {
                $send = $this->send($report->verpSendId, $report->originalMessageId);

                foreach ($report->recipients as $recipient) {
                    if ($recipient->isDelay()) {
                        $applyDeliveryEvent->handle(new DeliveryEvent(
                            type: DeliveryEventType::Deferred,
                            eventId: $message->id,
                            sendId: $send?->id,
                            recipient: $recipient->recipient,
                            diagnostic: $recipient->diagnostic,
                        ), 'dsn');

                        continue;
                    }

                    if (! $recipient->isHard() && ! $recipient->isSoft()) {
                        continue;
                    }

                    $applyBounce->handle(
                        $send->email ?? $recipient->recipient,
                        $send,
                        $recipient->isHard() ? BounceType::Hard : BounceType::Soft,
                        'dsn',
                        trim(sprintf('%s %s', $recipient->status, (string) $recipient->diagnostic)),
                    );
                }

                $processed++;
            }

            $reader->archive($message);
        }

        $this->info(sprintf('Processed %d delivery status notifications.', $processed));

        return self::SUCCESS;
    }

    private function send(?int $verpSendId, ?string $messageId): ?Send
    {
        if (! is_null($verpSendId)) {
            return Send::query()->find($verpSendId);
        }

        if (is_null($messageId) || $messageId === '') {
            return null;
        }

        return Send::query()
            ->where('message_id', $messageId)
            ->first();
    }
}
