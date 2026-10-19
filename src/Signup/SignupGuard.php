<?php

declare(strict_types=1);

namespace J87NL\Mailing\Signup;

use Illuminate\Http\Request;

class SignupGuard
{
    public const string HONEYPOT = 'website';

    public const string TIMESTAMP = 'form_token';

    public function token(int $listId): string
    {
        $time = now()->getTimestamp();

        return sprintf('%d.%s', $time, $this->signature($listId, $time));
    }

    public function looksHuman(Request $request, int $listId): bool
    {
        if (trim((string) $request->input(self::HONEYPOT, '')) !== '') {
            return false;
        }

        $parts = explode('.', (string) $request->input(self::TIMESTAMP, ''), 2);

        if (count($parts) !== 2 || ! ctype_digit($parts[0])) {
            return false;
        }

        $time = (int) $parts[0];

        if (! hash_equals($this->signature($listId, $time), $parts[1])) {
            return false;
        }

        $age = now()->getTimestamp() - $time;

        return $age >= (int) config('mailing.signup.min_seconds', 3) && $age <= (int) config('mailing.signup.max_seconds', 172800);
    }

    private function signature(int $listId, int $time): string
    {
        return hash_hmac('sha256', sprintf('mailing-signup|%d|%d', $listId, $time), (string) config('app.key'));
    }
}
