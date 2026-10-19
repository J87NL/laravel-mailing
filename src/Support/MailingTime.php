<?php

declare(strict_types=1);

namespace J87NL\Mailing\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

class MailingTime
{
    public static function timezone(): string
    {
        $timezone = config('mailing.timezone');

        if (is_string($timezone) && $timezone !== '') {
            return $timezone;
        }

        return (string) config('app.timezone', 'UTC');
    }

    public static function format(?DateTimeInterface $moment, string $format): ?string
    {
        if (is_null($moment)) {
            return null;
        }

        return CarbonImmutable::instance($moment)
            ->setTimezone(self::timezone())
            ->isoFormat($format);
    }

    public static function startOfDay(CarbonImmutable $moment): CarbonImmutable
    {
        return $moment->setTimezone(self::timezone())
            ->startOfDay()
            ->setTimezone((string) config('app.timezone', 'UTC'));
    }
}
