<?php

declare(strict_types=1);

namespace J87NL\Mailing\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use J87NL\Mailing\Enums\CampaignStatus;
use J87NL\Mailing\Enums\SendStatus;
use J87NL\Mailing\Models\Campaign;
use J87NL\Mailing\Models\Send;

class ChangeCampaignStatus
{
    public function schedule(Campaign $campaign, CarbonImmutable $at): bool
    {
        return $this->transition($campaign, [
            CampaignStatus::Draft,
            CampaignStatus::Scheduled,
        ], CampaignStatus::Scheduled, [
            'scheduled_at' => $at,
        ]);
    }

    public function unschedule(Campaign $campaign): bool
    {
        return $this->transition($campaign, [CampaignStatus::Scheduled], CampaignStatus::Draft, [
            'scheduled_at' => null,
        ]);
    }

    public function pause(Campaign $campaign): bool
    {
        return $this->transition($campaign, [CampaignStatus::Sending], CampaignStatus::Paused);
    }

    public function resume(Campaign $campaign): bool
    {
        return $this->transition($campaign, [CampaignStatus::Paused], CampaignStatus::Sending);
    }

    public function cancel(Campaign $campaign): bool
    {
        return DB::transaction(function () use ($campaign): bool {
            $changed = $this->transition($campaign, [
                CampaignStatus::Draft,
                CampaignStatus::Scheduled,
                CampaignStatus::Sending,
                CampaignStatus::Paused,
            ], CampaignStatus::Cancelled, [
                'finished_at' => now(),
            ]);

            if (! $changed) {
                return false;
            }

            Send::query()
                ->where('campaign_id', $campaign->id)
                ->whereIn('status', [
                    SendStatus::Queued,
                    SendStatus::Deferred,
                ])
                ->whereNull('handed_over_at')
                ->update([
                    'status' => SendStatus::Cancelled,
                    'updated_at' => now(),
                ]);

            return true;
        });
    }

    public function retryFailed(Campaign $campaign): int
    {
        return DB::transaction(function () use ($campaign): int {
            $locked = Campaign::query()
                ->whereKey($campaign->id)
                ->lockForUpdate()
                ->first();

            if (is_null($locked) || ! $locked->status->allowsRetry()) {
                return 0;
            }

            $retried = Send::query()
                ->where('campaign_id', $campaign->id)
                ->where('status', SendStatus::Failed)
                ->whereNull('handed_over_at')
                ->update([
                    'status' => SendStatus::Queued,
                    'next_attempt_at' => null,
                    'updated_at' => now(),
                ]);

            if ($retried > 0) {
                Campaign::query()
                    ->whereKey($campaign->id)
                    ->where('status', CampaignStatus::Sent)
                    ->update([
                        'status' => CampaignStatus::Sending,
                        'finished_at' => null,
                        'updated_at' => now(),
                    ]);
            }

            $campaign->refresh();

            return $retried;
        });
    }

    /**
     * @param  list<CampaignStatus>  $from
     * @param  array<string, mixed>  $attributes
     */
    private function transition(Campaign $campaign, array $from, CampaignStatus $to, array $attributes = []): bool
    {
        $changed = Campaign::query()
            ->whereKey($campaign->id)
            ->whereIn('status', $from)
            ->update([
                ...$attributes,
                'status' => $to,
                'updated_at' => now(),
            ]);

        $campaign->refresh();

        return $changed === 1;
    }
}
