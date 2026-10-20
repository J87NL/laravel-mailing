<?php

declare(strict_types=1);

namespace J87NL\Mailing\Sending;

class RetrySchedule
{
    public function delayAfter(int $attempts): ?int
    {
        $delays = config('mailing.sending.retry_minutes', [
            15,
            60,
            240,
        ]);

        if (! is_array($delays)) {
            return null;
        }

        $delay = array_values($delays)[$attempts - 1] ?? null;

        return is_numeric($delay) ? (int) $delay : null;
    }
}
