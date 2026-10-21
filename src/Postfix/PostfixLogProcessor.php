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
use J87NL\Mailing\Support\EmailAddress;

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

        if (! $known) {
            return;
        }

        PostfixQueueId::query()
            ->updateOrCreate([
                'queue_id' => $entry->queueId,
            ], [
                'message_id' => $entry->messageId,
            ]);

        $this->touch($entry->queueId);
    }

    private function forget(string $queueId): void
    {
        PostfixQueueId::query()
            ->where('queue_id', $queueId)
            ->delete();
    }

    private function touch(string $queueId): void
    {
        PostfixQueueId::query()
            ->where('queue_id', $queueId)
            ->update([
                'created_at' => now(),
            ]);
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

        $send = Send::query()
            ->where('message_id', $messageId)
            ->first();

        if (is_null($send) || ! $this->isRecipientOf($entry, $send)) {
            return false;
        }

        if ($type === DeliveryEventType::Deferred) {
            $this->touch($entry->queueId);
        }

        $eventId = hash('sha256', implode("\n", [
            $messageId,
            $entry->queueId,
            $entry->status,
            $entry->recipient,
            $entry->dsn,
            $entry->reply,
        ]));

        try {
            DB::transaction(function () use ($eventId, $entry, $send, $type): void {
                WebhookEvent::query()
                    ->create([
                        'driver' => self::DRIVER,
                        'event_id' => $eventId,
                        'event' => (string) $entry->status,
                    ]);

                $source = ConsentSource::make(ConsentSource::POSTFIX_LOG);

                $this->applyDeliveryEvent->handle($this->event($type, $eventId, $send, $entry), $source);

                if ($entry->type === PostfixLogEntryType::Expired) {
                    $this->applyDeliveryEvent->handle($this->event(DeliveryEventType::Failed, $eventId, $send, $entry), $source);
                }
            });
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    private function event(DeliveryEventType $type, string $eventId, Send $send, PostfixLogEntry $entry): DeliveryEvent
    {
        return new DeliveryEvent(
            type: $type,
            eventId: $eventId,
            sendId: $send->id,
            diagnostic: $this->diagnostic($entry),
        );
    }

    private function isRecipientOf(PostfixLogEntry $entry, Send $send): bool
    {
        if ($entry->type === PostfixLogEntryType::Expired) {
            return true;
        }

        $email = EmailAddress::normalize($send->email);

        return in_array($email, array_map(
            fn (string $address): string => EmailAddress::normalize($address),
            array_filter([
                $entry->recipient,
                $entry->originalRecipient,
            ], fn (?string $address): bool => ! is_null($address)),
        ), true);
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
