<?php

declare(strict_types=1);

namespace J87NL\Mailing\Postfix;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use J87NL\Mailing\Actions\ApplyDeliveryEvent;
use J87NL\Mailing\Delivery\DeliveryEvent;
use J87NL\Mailing\Enums\DeliveryEventType;
use J87NL\Mailing\Models\PostfixQueueId;
use J87NL\Mailing\Models\Send;
use J87NL\Mailing\Models\WebhookEvent;
use J87NL\Mailing\Support\ConsentSource;

class PostfixLogProcessor
{
    public const string DRIVER = 'postfix-log';

    public function __construct(
        private readonly PostfixLogParser $parser,
        private readonly ApplyDeliveryEvent $applyDeliveryEvent,
    ) {}

    /**
     * @param  iterable<string>  $lines
     */
    public function process(iterable $lines): int
    {
        PostfixQueueId::query()
            ->where('created_at', '<', now()->subHours((int) config('mailing.postfix_log.queue_ttl_hours', 48)))
            ->delete();

        $applied = 0;

        foreach ($lines as $line) {
            $entry = $this->parser->parse($line);

            if (is_null($entry)) {
                continue;
            }

            if ($entry->type === PostfixLogEntryType::MessageId) {
                $this->remember($entry);

                continue;
            }

            if ($entry->type === PostfixLogEntryType::Removed) {
                $this->forget($entry->queueId);

                continue;
            }

            if ($this->apply($entry)) {
                $applied++;
            }
        }

        return $applied;
    }

    private function remember(PostfixLogEntry $entry): void
    {
        $known = Send::query()
            ->where('message_id', $entry->messageId)
            ->exists();

        $this->forget($entry->queueId);

        if (! $known) {
            return;
        }

        PostfixQueueId::query()
            ->create([
                'queue_id' => $entry->queueId,
                'message_id' => $entry->messageId,
            ]);
    }

    private function forget(string $queueId): void
    {
        PostfixQueueId::query()
            ->where('queue_id', $queueId)
            ->delete();
    }

    private function apply(PostfixLogEntry $entry): bool
    {
        $messageId = PostfixQueueId::query()
            ->where('queue_id', $entry->queueId)
            ->value('message_id');

        $type = $this->type($entry);

        if (! is_string($messageId) || is_null($type)) {
            return false;
        }

        $event = new DeliveryEvent(
            type: $type,
            eventId: hash('sha256', implode("\n", [
                $entry->queueId,
                $entry->status,
                $entry->recipient,
                $entry->dsn,
                $entry->reply,
            ])),
            messageId: $messageId,
            diagnostic: $this->diagnostic($entry),
        );

        try {
            DB::transaction(function () use ($event, $entry): void {
                WebhookEvent::query()
                    ->create([
                        'driver' => self::DRIVER,
                        'event_id' => $event->eventId,
                        'event' => (string) $entry->status,
                    ]);

                $this->applyDeliveryEvent->handle($event, ConsentSource::make(ConsentSource::POSTFIX_LOG));
            });
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    private function type(PostfixLogEntry $entry): ?DeliveryEventType
    {
        if ($entry->type === PostfixLogEntryType::Expired) {
            return DeliveryEventType::SoftBounce;
        }

        return match (true) {
            $entry->status === 'sent' => DeliveryEventType::Delivered,
            $entry->status === 'deferred' => DeliveryEventType::Deferred,
            $entry->status === 'bounced' && str_starts_with((string) $entry->dsn, '5.') => DeliveryEventType::HardBounce,
            $entry->status === 'bounced' && str_starts_with((string) $entry->dsn, '4.') => DeliveryEventType::SoftBounce,
            default => null,
        };
    }

    private function diagnostic(PostfixLogEntry $entry): ?string
    {
        $parts = array_filter([
            $entry->status === 'sent' ? null : $entry->dsn,
            $entry->reply,
        ]);

        return $parts === [] ? null : implode(' ', $parts);
    }
}
