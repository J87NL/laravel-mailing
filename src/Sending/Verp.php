<?php

declare(strict_types=1);

namespace J87NL\Mailing\Sending;

final class Verp
{
    private const int SIGNATURE_LENGTH = 16;

    public static function address(int $sendId): ?string
    {
        $domain = config('mailing.bounces.domain');

        if (! is_string($domain) || $domain === '') {
            return null;
        }

        return sprintf('%s+%d-%s@%s', self::localPart(), $sendId, self::signature($sendId), $domain);
    }

    public static function sendId(string $address): ?int
    {
        $pattern = sprintf('/^%s\+(\d+)-([a-f0-9]{%d})@/i', preg_quote(self::localPart(), '/'), self::SIGNATURE_LENGTH);

        if (preg_match($pattern, trim($address, " <>\t"), $matches) !== 1) {
            return null;
        }

        $sendId = (int) $matches[1];

        return hash_equals(self::signature($sendId), strtolower($matches[2])) ? $sendId : null;
    }

    private static function localPart(): string
    {
        return (string) config('mailing.bounces.local_part', 'bounces');
    }

    private static function signature(int $sendId): string
    {
        return substr(hash_hmac('sha256', sprintf('mailing-verp|%d', $sendId), (string) config('app.key')), 0, self::SIGNATURE_LENGTH);
    }
}
