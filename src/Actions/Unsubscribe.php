<?php

declare(strict_types=1);

namespace J87NL\Mailing\Actions;

use Illuminate\Support\Facades\DB;
use J87NL\Mailing\Enums\ConsentAction;
use J87NL\Mailing\Enums\SubscriptionStatus;
use J87NL\Mailing\Models\Subscription;

class Unsubscribe
{
    public function __construct(
        private readonly RecordConsent $recordConsent,
    ) {}

    public function handle(Subscription $subscription, string $source, ?string $ip = null, ?int $actorId = null, SubscriptionStatus $status = SubscriptionStatus::Unsubscribed): bool
    {
        return DB::transaction(function () use ($subscription, $source, $ip, $actorId, $status): bool {
            $subscription = Subscription::query()
                ->with([
                    'subscriber',
                    'mailingList',
                ])
                ->lockForUpdate()
                ->findOrFail($subscription->id);

            if (! $subscription->isActive()) {
                return false;
            }

            $subscription->forceFill([
                'status' => $status,
                'unsubscribed_at' => now(),
            ])->save();

            $this->recordConsent->handle($subscription->subscriber, $subscription->mailingList, ConsentAction::Unsubscribed, $source, $ip, null, $actorId);

            return true;
        });
    }
}
