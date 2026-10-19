<?php

declare(strict_types=1);

namespace J87NL\Mailing\Sending;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use J87NL\Mailing\Models\MailingList;
use J87NL\Mailing\Models\RateCounter;
use J87NL\Mailing\Support\EmailAddress;
use J87NL\Mailing\Support\MailingTime;

class SendingLimits
{
    public function claim(MailingList $list, string $email, CarbonImmutable $now): bool
    {
        $limits = [
            [
                sprintf('list:%d:minute', $list->id),
                $now->startOfMinute(),
                max(1, $list->throttle_per_minute),
            ],
        ];

        if (! is_null($list->daily_cap)) {
            $limits[] = [
                sprintf('list:%d:day', $list->id),
                MailingTime::startOfDay($now),
                $list->daily_cap,
            ];
        }

        $domain = EmailAddress::domain($email);
        $domainLimit = $this->domainLimit($domain);

        if (! is_null($domainLimit)) {
            $limits[] = [
                sprintf('domain:%s:minute', $domain),
                $now->startOfMinute(),
                $domainLimit,
            ];
        }

        return DB::transaction(function () use ($limits): bool {
            $counters = [];

            foreach ($limits as [$key, $window, $limit]) {
                $counter = RateCounter::query()
                    ->where('key', $key)
                    ->where('window_starts_at', $window)
                    ->lockForUpdate()
                    ->first() ?? new RateCounter([
                        'key' => $key,
                        'window_starts_at' => $window,
                        'hits' => 0,
                    ]);

                if ($counter->hits >= $limit) {
                    return false;
                }

                $counters[] = $counter;
            }

            foreach ($counters as $counter) {
                $counter->hits++;
                $counter->save();
            }

            return true;
        });
    }

    public function prune(CarbonImmutable $before): int
    {
        return RateCounter::query()
            ->where('window_starts_at', '<', $before)
            ->delete();
    }

    private function domainLimit(string $domain): ?int
    {
        $limits = config('mailing.sending.domain_limits', []);

        if (! is_array($limits) || ! isset($limits[$domain])) {
            return null;
        }

        return max(1, (int) $limits[$domain]);
    }
}
