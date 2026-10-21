<?php

declare(strict_types=1);

namespace J87NL\Mailing\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use J87NL\Mailing\Attachments\CampaignAttachments;
use J87NL\Mailing\Enums\CampaignStatus;
use J87NL\Mailing\Enums\ConsentAction;
use J87NL\Mailing\Enums\SubscriptionStatus;
use J87NL\Mailing\Models\BounceEvent;
use J87NL\Mailing\Models\Campaign;
use J87NL\Mailing\Models\CampaignAttachment;
use J87NL\Mailing\Models\ConsentRecord;
use J87NL\Mailing\Models\Send;
use J87NL\Mailing\Models\Subscriber;
use J87NL\Mailing\Models\Subscription;
use J87NL\Mailing\Models\WebhookEvent;

class PruneMailingData extends Command
{
    protected $signature = 'mailing:prune';

    protected $description = 'Delete unconfirmed sign-ups and expired consent records of unsubscribed addresses, and attachments of finished campaigns';

    public function handle(CampaignAttachments $campaignAttachments): int
    {
        $unconfirmed = $this->pruneUnconfirmed(
            Subscription::query()
                ->where('status', SubscriptionStatus::Pending)
                ->where('updated_at', '<', now()->subDays((int) config('mailing.retention.unconfirmed_days', 30))),
        );

        $ended = $this->prune(
            Subscription::query()
                ->whereIn('status', [
                    SubscriptionStatus::Unsubscribed,
                    SubscriptionStatus::Bounced,
                    SubscriptionStatus::Complained,
                ])
                ->where('unsubscribed_at', '<', now()->subYears((int) config('mailing.retention.unsubscribed_years', 2))),
        );

        $sendCutoff = now()->subMonths((int) config('mailing.retention.send_records_months', 13));

        $sends = Send::query()
            ->where('created_at', '<', $sendCutoff)
            ->delete();

        BounceEvent::query()
            ->where('created_at', '<', $sendCutoff)
            ->delete();

        WebhookEvent::query()
            ->where('created_at', '<', $sendCutoff)
            ->delete();

        $attachments = 0;

        Campaign::query()
            ->whereIn('status', [
                CampaignStatus::Sent,
                CampaignStatus::Cancelled,
            ])
            ->where('finished_at', '<', now()->subDays($campaignAttachments->retentionDays()))
            ->whereHas('attachments')
            ->chunkById(50, function (Collection $campaigns) use (&$attachments): void {
                foreach ($campaigns as $campaign) {
                    $attachments += $this->pruneAttachments($campaign);
                }
            });

        $subscribers = Subscriber::query()
            ->whereDoesntHave('subscriptions')
            ->where('updated_at', '<', now()->subDays((int) config('mailing.retention.unconfirmed_days', 30)))
            ->delete();

        $this->info(sprintf('Pruned or ended %d unconfirmed and pruned %d ended subscriptions, %d subscribers, %d send records and %d attachments.', $unconfirmed, $ended, $subscribers, $sends, $attachments));

        return self::SUCCESS;
    }

    /**
     * @param  Builder<Subscription>  $query
     */
    private function pruneUnconfirmed(Builder $query): int
    {
        $count = 0;

        $query->chunkById(200, function (Collection $subscriptions) use (&$count): void {
            foreach ($subscriptions as $subscription) {
                DB::transaction(function () use ($subscription): void {
                    $earlier = ConsentRecord::query()
                        ->where('subscriber_id', $subscription->subscriber_id)
                        ->where('list_id', $subscription->list_id)
                        ->where('action', ConsentAction::Unsubscribed)
                        ->orderByDesc('id')
                        ->first();

                    if (is_null($earlier)) {
                        $this->deleteWithRecords($subscription);

                        return;
                    }

                    ConsentRecord::query()
                        ->where('subscriber_id', $subscription->subscriber_id)
                        ->where('list_id', $subscription->list_id)
                        ->whereIn('action', [
                            ConsentAction::Requested,
                            ConsentAction::Imported,
                        ])
                        ->where('id', '>', $earlier->id)
                        ->delete();

                    $confirmed = ConsentRecord::query()
                        ->where('subscriber_id', $subscription->subscriber_id)
                        ->where('list_id', $subscription->list_id)
                        ->whereIn('action', [
                            ConsentAction::Confirmed,
                            ConsentAction::Imported,
                        ])
                        ->where('id', '<', $earlier->id)
                        ->orderByDesc('id')
                        ->first();

                    $subscription->forceFill([
                        'status' => SubscriptionStatus::Unsubscribed,
                        'requested_name' => null,
                        'confirmed_at' => $confirmed?->created_at,
                        'unsubscribed_at' => $earlier->created_at,
                    ])->save();
                });

                $count++;
            }
        });

        return $count;
    }

    /**
     * @param  Builder<Subscription>  $query
     */
    private function prune(Builder $query): int
    {
        $count = 0;

        $query->chunkById(200, function (Collection $subscriptions) use (&$count): void {
            foreach ($subscriptions as $subscription) {
                DB::transaction(fn () => $this->deleteWithRecords($subscription));

                $count++;
            }
        });

        return $count;
    }

    private function pruneAttachments(Campaign $campaign): int
    {
        $attachments = $campaign->attachments()
            ->get();

        DB::transaction(fn () => Model::withoutTouchingOn([Campaign::class], fn () => Campaign::withoutTimestamps(function () use ($campaign, $attachments): void {
            $attachments->each(fn (CampaignAttachment $attachment) => $attachment->delete());

            $campaign->forceFill([
                'attachments_pruned_at' => now(),
            ])->save();
        })));

        return $attachments->count();
    }

    private function deleteWithRecords(Subscription $subscription): void
    {
        ConsentRecord::query()
            ->where('subscriber_id', $subscription->subscriber_id)
            ->where('list_id', $subscription->list_id)
            ->delete();

        $subscription->delete();
    }
}
