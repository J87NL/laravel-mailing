<?php

declare(strict_types=1);

namespace J87NL\Mailing\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use J87NL\Mailing\Enums\SubscriptionStatus;
use J87NL\Mailing\Models\ConsentRecord;
use J87NL\Mailing\Models\Subscriber;
use J87NL\Mailing\Models\Subscription;

class PruneMailingData extends Command
{
    protected $signature = 'mailing:prune';

    protected $description = 'Delete unconfirmed sign-ups and expired consent records of unsubscribed addresses';

    public function handle(): int
    {
        $unconfirmed = $this->prune(
            Subscription::query()
                ->where('status', SubscriptionStatus::Pending)
                ->whereNull('confirmed_at')
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

        $subscribers = Subscriber::query()
            ->whereDoesntHave('subscriptions')
            ->where('updated_at', '<', now()->subDays((int) config('mailing.retention.unconfirmed_days', 30)))
            ->delete();

        $this->info(sprintf('Pruned %d unconfirmed and %d ended subscriptions, and %d subscribers.', $unconfirmed, $ended, $subscribers));

        return self::SUCCESS;
    }

    /**
     * @param  Builder<Subscription>  $query
     */
    private function prune(Builder $query): int
    {
        $count = 0;

        $query->chunkById(200, function (Collection $subscriptions) use (&$count): void {
            foreach ($subscriptions as $subscription) {
                DB::transaction(function () use ($subscription): void {
                    ConsentRecord::query()
                        ->where('subscriber_id', $subscription->subscriber_id)
                        ->where('list_id', $subscription->list_id)
                        ->delete();

                    $subscription->delete();
                });

                $count++;
            }
        });

        return $count;
    }
}
