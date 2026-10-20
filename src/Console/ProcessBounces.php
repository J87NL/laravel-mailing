<?php

declare(strict_types=1);

namespace J87NL\Mailing\Console;

use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use J87NL\Mailing\Actions\ApplyBounce;
use J87NL\Mailing\Actions\ApplyDeliveryEvent;
use J87NL\Mailing\Bounces\DsnParser;
use J87NL\Mailing\Bounces\DsnReport;
use J87NL\Mailing\Contracts\MailboxReader;
use J87NL\Mailing\Delivery\DeliveryEvent;
use J87NL\Mailing\Enums\BounceType;
use J87NL\Mailing\Enums\DeliveryEventType;
use J87NL\Mailing\Mailbox\MailboxMessage;
use J87NL\Mailing\Models\Send;
use J87NL\Mailing\Models\WebhookEvent;
use J87NL\Mailing\Support\ConsentSource;

class ProcessBounces extends Command
{
    private const string DSN_DRIVER = 'dsn';

    protected $signature = 'mailing:process-bounces';

    protected $description = 'Read the bounce mailbox and apply the delivery status notifications';

    public function handle(MailboxReader $reader, DsnParser $parser, ApplyBounce $applyBounce, ApplyDeliveryEvent $applyDeliveryEvent): int
    {
        $processed = 0;

        foreach ($reader->unprocessed() as $message) {
            $report = $parser->parse($message->raw);

            $send = is_null($report) ? null : $this->send($report->verpSendId, $report->originalMessageId);

            if (! is_null($report) && ! is_null($send) && $this->applyOnce($message, $report, $send, $applyBounce, $applyDeliveryEvent)) {
                $processed++;
            }

            $reader->archive($message);
        }

        $this->info(sprintf('Processed %d delivery status notifications.', $processed));

        return self::SUCCESS;
    }

    private function applyOnce(MailboxMessage $message, DsnReport $report, Send $send, ApplyBounce $applyBounce, ApplyDeliveryEvent $applyDeliveryEvent): bool
    {
        return DB::transaction(function () use ($message, $report, $send, $applyBounce, $applyDeliveryEvent): bool {
            try {
                DB::transaction(fn (): WebhookEvent => WebhookEvent::query()
                    ->create([
                        'driver' => self::DSN_DRIVER,
                        'event_id' => hash('sha256', $message->raw),
                        'event' => self::DSN_DRIVER,
                    ]));
            } catch (UniqueConstraintViolationException) {
                return false;
            }

            $this->apply($report, $send, $message->id, $applyBounce, $applyDeliveryEvent);

            return true;
        });
    }

    private function apply(DsnReport $report, Send $send, string $messageId, ApplyBounce $applyBounce, ApplyDeliveryEvent $applyDeliveryEvent): void
    {
        $source = ConsentSource::make(ConsentSource::DSN);

        foreach ($report->recipients as $recipient) {
            if ($recipient->isDelay()) {
                $applyDeliveryEvent->handle(new DeliveryEvent(
                    type: DeliveryEventType::Deferred,
                    eventId: $messageId,
                    sendId: $send->id,
                    recipient: $send->email,
                    diagnostic: $recipient->diagnostic,
                ), $source);

                continue;
            }

            if (! $recipient->isHard() && ! $recipient->isSoft()) {
                continue;
            }

            $applyBounce->handle(
                $send->email,
                $send,
                $recipient->isHard() ? BounceType::Hard : BounceType::Soft,
                $source,
                trim(sprintf('%s %s', $recipient->status, (string) $recipient->diagnostic)),
            );
        }
    }

    private function send(?int $verpSendId, ?string $messageId): ?Send
    {
        if (! is_null($verpSendId)) {
            return Send::query()
                ->find($verpSendId);
        }

        if (is_null($messageId) || $messageId === '') {
            return null;
        }

        return Send::query()
            ->where('message_id', $messageId)
            ->first();
    }
}
