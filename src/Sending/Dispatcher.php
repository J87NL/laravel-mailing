<?php

declare(strict_types=1);

namespace J87NL\Mailing\Sending;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use J87NL\Mailing\Actions\StartCampaign;
use J87NL\Mailing\Enums\CampaignStatus;
use J87NL\Mailing\Enums\SendStatus;
use J87NL\Mailing\Jobs\SendCampaignMessage;
use J87NL\Mailing\Models\Campaign;
use J87NL\Mailing\Models\Send;

class Dispatcher
{
    public const string INTERRUPTED = 'interrupted';

    public function __construct(
        private readonly SendingLimits $limits,
        private readonly StartCampaign $startCampaign,
    ) {}

    /**
     * @return array{started: int, dispatched: int, interrupted: int, finished: int}
     */
    public function run(CarbonImmutable $now): array
    {
        $interrupted = $this->failStaleSends($now);
        $started = 0;

        $due = Campaign::query()
            ->where('status', CampaignStatus::Scheduled)
            ->where('scheduled_at', '<=', $now)
            ->get();

        foreach ($due as $campaign) {
            $started += $this->startCampaign->handle($campaign) ? 1 : 0;
        }

        $dispatched = 0;

        $sending = Campaign::query()
            ->with('mailingList')
            ->where('status', CampaignStatus::Sending)
            ->orderBy('started_at')
            ->get();

        foreach ($sending as $campaign) {
            $dispatched += $this->dispatchFor($campaign, $now);
        }

        $finished = 0;

        foreach ($sending as $campaign) {
            $finished += $this->finishIfDone($campaign, $now) ? 1 : 0;
        }

        $this->limits->prune($now->subDays(2));

        return [
            'started' => $started,
            'dispatched' => $dispatched,
            'interrupted' => $interrupted,
            'finished' => $finished,
        ];
    }

    private function dispatchFor(Campaign $campaign, CarbonImmutable $now): int
    {
        $budget = max(1, $campaign->mailingList->throttle_per_minute) * (int) config('mailing.sending.scan_factor', 5);

        $candidates = Send::query()
            ->where('campaign_id', $campaign->id)
            ->where(fn (Builder $query): Builder => $query
                ->where(fn (Builder $queued): Builder => $queued
                    ->where('status', SendStatus::Queued)
                    ->where(fn (Builder $lease): Builder => $lease
                        ->whereNull('next_attempt_at')
                        ->orWhere('next_attempt_at', '<=', $now)))
                ->orWhere(fn (Builder $deferred): Builder => $deferred
                    ->where('status', SendStatus::Deferred)
                    ->whereNotNull('next_attempt_at')
                    ->where('next_attempt_at', '<=', $now)))
            ->orderBy('id')
            ->limit($budget)
            ->get();

        $dispatched = 0;
        $leaseEnds = $now->startOfMinute()->addMinute();

        foreach ($candidates as $send) {
            if (! $this->limits->claim($campaign->mailingList, $send->email, $now)) {
                continue;
            }

            $send->forceFill([
                'next_attempt_at' => $leaseEnds,
            ])->save();

            SendCampaignMessage::dispatch($send->id, $send->attempts, $leaseEnds->getTimestamp());
            $dispatched++;
        }

        return $dispatched;
    }

    private function failStaleSends(CarbonImmutable $now): int
    {
        return Send::query()
            ->where('status', SendStatus::Sending)
            ->where('sending_started_at', '<', $now->subMinutes((int) config('mailing.sending.stale_minutes', 30)))
            ->update([
                'status' => SendStatus::Failed,
                'last_response' => self::INTERRUPTED,
                'updated_at' => $now,
            ]);
    }

    private function finishIfDone(Campaign $campaign, CarbonImmutable $now): bool
    {
        $open = $campaign->sends()
            ->whereIn('status', [
                SendStatus::Queued,
                SendStatus::Sending,
            ])
            ->orWhere(fn (Builder $query): Builder => $query
                ->where('campaign_id', $campaign->id)
                ->where('status', SendStatus::Deferred)
                ->whereNotNull('next_attempt_at'))
            ->exists();

        if ($open) {
            return false;
        }

        return Campaign::query()
            ->whereKey($campaign->id)
            ->where('status', CampaignStatus::Sending)
            ->update([
                'status' => CampaignStatus::Sent,
                'finished_at' => $now,
                'updated_at' => $now,
            ]) === 1;
    }
}
