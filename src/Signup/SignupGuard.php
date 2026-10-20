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

    public function verdict(Request $request, int $listId): SignupVerdict
    {
        if (trim((string) $request->input(self::HONEYPOT, '')) !== '') {
            return SignupVerdict::Bot;
        }

        $parts = explode('.', (string) $request->input(self::TIMESTAMP, ''), 2);

        if (count($parts) !== 2 || ! ctype_digit($parts[0])) {
            return SignupVerdict::Bot;
        }

        $time = (int) $parts[0];

        if (! hash_equals($this->signature($listId, $time), $parts[1])) {
            return SignupVerdict::Bot;
        }

        $age = now()->getTimestamp() - $time;

        if ($age < (int) config('mailing.signup.min_seconds', 3)) {
            return SignupVerdict::Bot;
        }

        if ($age > (int) config('mailing.signup.max_seconds', 172800)) {
            return SignupVerdict::Expired;
        }

        return SignupVerdict::Human;
    }

    private function signature(int $listId, int $time): string
    {
        return hash_hmac('sha256', sprintf('mailing-signup|%d|%d', $listId, $time), (string) config('app.key'));
    }
}
