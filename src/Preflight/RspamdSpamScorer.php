<?php

declare(strict_types=1);

namespace J87NL\Mailing\Preflight;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use J87NL\Mailing\Contracts\SpamScorer;

class RspamdSpamScorer implements SpamScorer
{
    public function score(string $rawMessage): ?float
    {
        $url = config('mailing.preflight.rspamd.url');

        if (! is_string($url) || $url === '') {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(array_filter([
                    'Password' => config('mailing.preflight.rspamd.password'),
                ], fn (mixed $value): bool => is_string($value) && $value !== ''))
                ->withBody($rawMessage, 'message/rfc822')
                ->post(sprintf('%s/checkv2', rtrim($url, '/')));
        } catch (ConnectionException) {
            return null;
        }

        $score = $response->json('score');

        return $response->successful() && is_numeric($score) ? (float) $score : null;
    }
}
