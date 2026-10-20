<?php

declare(strict_types=1);

namespace J87NL\Mailing\Signup;

use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use J87NL\Mailing\Support\EmailAddress;

class SignupLimiter
{
    private const int DECAY_SECONDS = 3600;

    private const int DAY_SECONDS = 86400;

    private const string GLOBAL_KEY = 'mailing-signup:global';

    public function __construct(
        private readonly RateLimiter $limiter,
    ) {}

    public function tooManyFromIp(Request $request): bool
    {
        return $this->limiter->tooManyAttempts($this->ipKey($request), (int) config('mailing.signup.per_ip_per_hour', 10));
    }

    public function hitIp(Request $request): void
    {
        $this->limiter->hit($this->ipKey($request), self::DECAY_SECONDS);
    }

    public function claimAddress(string $email): bool
    {
        $hourly = $this->limiter->hit($this->addressKey($email), self::DECAY_SECONDS);
        $daily = $this->limiter->hit($this->addressKey($email, 'day'), self::DAY_SECONDS);

        return $hourly <= (int) config('mailing.signup.per_address_per_hour', 3)
            && $daily <= (int) config('mailing.signup.per_address_per_day', 6);
    }

    public function claimOverall(): bool
    {
        return $this->limiter->hit(self::GLOBAL_KEY, self::DECAY_SECONDS) <= (int) config('mailing.signup.global_per_hour', 60);
    }

    public function retryAfterForIp(Request $request): int
    {
        return max(1, $this->limiter->availableIn($this->ipKey($request)));
    }

    public function retryAfterOverall(): int
    {
        return max(1, $this->limiter->availableIn(self::GLOBAL_KEY));
    }

    private function ipKey(Request $request): string
    {
        return sprintf('mailing-signup:ip:%s', sha1((string) $request->ip()));
    }

    private function addressKey(string $email, string $window = 'hour'): string
    {
        return sprintf('mailing-signup:address:%s:%s', $window, sha1(EmailAddress::normalize($email)));
    }
}
