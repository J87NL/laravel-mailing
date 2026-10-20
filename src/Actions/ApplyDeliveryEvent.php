<?php

declare(strict_types=1);

namespace J87NL\Mailing\Actions;

use J87NL\Mailing\Delivery\DeliveryEvent;
use J87NL\Mailing\Enums\BounceType;
use J87NL\Mailing\Enums\DeliveryEventType;
use J87NL\Mailing\Enums\SendStatus;
use J87NL\Mailing\Enums\SuppressionReason;
use J87NL\Mailing\Models\Send;
use J87NL\Mailing\Models\Suppression;

class ApplyDeliveryEvent
{
    public function __construct(
        private readonly ApplyBounce $applyBounce,
        private readonly SuppressAddress $suppressAddress,
        private readonly Unsubscribe $unsubscribe,
    ) {}

    public function handle(DeliveryEvent $event, string $source): void
    {
        $send = $this->send($event);
        $email = $event->recipient ?? $send?->email;

        match ($event->type) {
            DeliveryEventType::Delivered => $this->update($send, SendStatus::Delivered, [
                'delivered_at' => now(),
            ]),
            DeliveryEventType::Deferred => $this->update($send, SendStatus::Deferred, [
                'next_attempt_at' => null,
                'last_response' => $event->diagnostic,
            ]),
            DeliveryEventType::Failed => $this->update($send, SendStatus::Failed, [
                'last_response' => $event->diagnostic,
            ]),
            DeliveryEventType::HardBounce => is_null($email) ? null : $this->applyBounce->handle($email, $send, BounceType::Hard, $source, $event->diagnostic),
            DeliveryEventType::SoftBounce => is_null($email) ? null : $this->applyBounce->handle($email, $send, BounceType::Soft, $source, $event->diagnostic),
            DeliveryEventType::Complained => $this->complained($email, $send, $source),
            DeliveryEventType::Unsubscribed => is_null($send?->subscription) ? null : $this->unsubscribe->handle($send->subscription, $source),
            DeliveryEventType::SuppressionAdded => is_null($email) ? null : $this->suppressAddress->handle($email, SuppressionReason::Manual, $source, $event->diagnostic),
            DeliveryEventType::SuppressionRemoved => is_null($email) ? null : Suppression::query()
                ->where('email', strtolower($email))
                ->where('source', $source)
                ->where('reason', SuppressionReason::Manual)
                ->delete(),
        };
    }

    private function complained(?string $email, ?Send $send, string $source): void
    {
        $this->update($send, SendStatus::Complained);

        if (is_null($email)) {
            return;
        }

        $this->suppressAddress->handle($email, SuppressionReason::Complaint, $source);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function update(?Send $send, SendStatus $status, array $attributes = []): void
    {
        if (is_null($send)) {
            return;
        }

        $send->advanceTo($status, $attributes);
    }

    private function send(DeliveryEvent $event): ?Send
    {
        if (! is_null($event->sendId)) {
            return Send::query()
                ->with('subscription')
                ->find($event->sendId);
        }

        if (is_null($event->messageId)) {
            return null;
        }

        return Send::query()
            ->with('subscription')
            ->whereIn('message_id', [
                $event->messageId,
                sprintf('<%s>', trim($event->messageId, '<>')),
            ])
            ->first();
    }
}
