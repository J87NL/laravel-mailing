<?php

declare(strict_types=1);

namespace J87NL\Mailing\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use J87NL\Mailing\Enums\CampaignStatus;
use J87NL\Mailing\Enums\SendStatus;
use J87NL\Mailing\Enums\SubscriptionStatus;
use J87NL\Mailing\Models\Campaign;
use J87NL\Mailing\Models\Send;
use J87NL\Mailing\Models\Subscription;
use J87NL\Mailing\Models\Suppression;

class StartCampaign
{
    private const array STARTABLE = [
        CampaignStatus::Draft,
        CampaignStatus::Scheduled,
    ];

    private const int CHUNK = 500;

    public function handle(Campaign $campaign): bool
    {
        return DB::transaction(function () use ($campaign): bool {
            $campaign = Campaign::query()
                ->lockForUpdate()
                ->findOrFail($campaign->id);

            if (! in_array($campaign->status, self::STARTABLE, true)) {
                return false;
            }

            Subscription::query()
                ->with('subscriber')
                ->where('list_id', $campaign->list_id)
                ->where('status', SubscriptionStatus::Subscribed)
                ->whereNotNull('confirmed_at')
                ->whereDoesntHave('subscriber', fn (Builder $query): Builder => $query->whereIn('email', Suppression::query()
                    ->select('email')))
                ->chunkById(self::CHUNK, function ($subscriptions) use ($campaign): void {
                    $now = now();
                    $rows = [];

                    foreach ($subscriptions as $subscription) {
                        $rows[] = [
                            'campaign_id' => $campaign->id,
                            'subscription_id' => $subscription->id,
                            'email' => $subscription->subscriber->email,
                            'status' => SendStatus::Queued->value,
                            'attempts' => 0,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    Send::query()
                        ->insertOrIgnore($rows);
                });

            $campaign->forceFill([
                'status' => CampaignStatus::Sending,
                'started_at' => now(),
            ])->save();

            return true;
        });
    }
}
