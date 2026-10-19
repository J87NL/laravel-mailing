<?php

declare(strict_types=1);

namespace J87NL\Mailing\Actions;

use Illuminate\Support\Facades\DB;
use J87NL\Mailing\Enums\ConsentAction;
use J87NL\Mailing\Enums\SubscriptionStatus;
use J87NL\Mailing\Models\Subscriber;
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

            $requestedName = $subscription->requested_name;

            $subscription->forceFill([
                'status' => SubscriptionStatus::Subscribed,
                'consent_at' => now(),
                'consent_ip' => $ip,
                'confirmed_at' => now(),
                'requested_name' => null,
            ])->save();

            $this->adoptRequestedName($subscription->subscriber, $requestedName);

            $this->recordConsent->handle($subscription->subscriber, $subscription->mailingList, ConsentAction::Confirmed, $subscription->consent_source, $ip);

            return true;
        });
    }

    private function adoptRequestedName(Subscriber $subscriber, ?string $requestedName): void
    {
        if (is_null($requestedName) || $requestedName === '' || (! is_null($subscriber->name) && $subscriber->name !== '')) {
            return;
        }

        $subscriber->name = $requestedName;
        $subscriber->save();
    }
}
