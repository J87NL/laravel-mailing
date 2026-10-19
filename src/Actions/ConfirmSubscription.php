<?php

declare(strict_types=1);

namespace J87NL\Mailing\Actions;

use Illuminate\Support\Facades\DB;
use J87NL\Mailing\Enums\ConsentAction;
use J87NL\Mailing\Enums\SubscriptionStatus;
use J87NL\Mailing\Models\Subscription;

class ConfirmSubscription
{
    public function __construct(
        private readonly RecordConsent $recordConsent,
    ) {}

    public function handle(Subscription $subscription, ?string $ip): bool
    {
        return DB::transaction(function () use ($subscription, $ip): bool {
            $subscription = Subscription::query()
                ->with([
                    'subscriber',
                    'mailingList',
                ])
                ->lockForUpdate()
                ->findOrFail($subscription->id);

            if ($subscription->status !== SubscriptionStatus::Pending || $subscription->subscriber->isSuppressed()) {
                return false;
            }

            $subscription->forceFill([
                'status' => SubscriptionStatus::Subscribed,
                'consent_at' => now(),
                'consent_ip' => $ip,
                'confirmed_at' => now(),
            ])->save();

            $this->recordConsent->handle($subscription->subscriber, $subscription->mailingList, ConsentAction::Confirmed, $subscription->consent_source, $ip);

            return true;
        });
    }
}
