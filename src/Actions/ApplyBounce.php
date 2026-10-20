<?php

declare(strict_types=1);

namespace J87NL\Mailing\Actions;

use Illuminate\Database\Eloquent\Builder;
use J87NL\Mailing\Enums\BounceType;
use J87NL\Mailing\Enums\SendStatus;
use J87NL\Mailing\Enums\SuppressionReason;
use J87NL\Mailing\Models\BounceEvent;
use J87NL\Mailing\Models\Send;
use J87NL\Mailing\Support\EmailAddress;

class ApplyBounce
{
    public function __construct(
        private readonly SuppressAddress $suppressAddress,
    ) {}

    public function handle(string $email, ?Send $send, BounceType $type, string $source, ?string $diagnostic): void
    {
        BounceEvent::query()
            ->create([
                'email' => $email,
                'send_id' => $send?->id,
                'type' => $type,
                'source' => $source,
                'diagnostic' => $diagnostic,
            ]);

        $send?->advanceTo($type === BounceType::Hard ? SendStatus::Bounced : SendStatus::Deferred, [
            'next_attempt_at' => null,
            'bounced_at' => now(),
            'bounce_type' => $type,
            'bounce_diagnostic' => $diagnostic,
        ]);

        if ($type === BounceType::Hard) {
            $this->suppressAddress->handle($email, SuppressionReason::HardBounce, $source, $diagnostic);

            return;
        }

        if ($this->softBouncedMessages($email) < (int) config('mailing.bounces.soft_limit', 3)) {
            return;
        }

        $send?->advanceTo(SendStatus::Bounced);

        $this->suppressAddress->handle($email, SuppressionReason::SoftBounces, $source, $diagnostic);
    }

    private function softBouncedMessages(string $email): int
    {
        $recent = fn (): Builder => BounceEvent::query()
            ->where('email', EmailAddress::normalize($email))
            ->where('type', BounceType::Soft)
            ->where('created_at', '>=', now()->subDays((int) config('mailing.bounces.soft_window_days', 30)));

        $messages = $recent()
            ->whereNotNull('send_id')
            ->distinct()
            ->count('send_id');

        $withoutSend = $recent()
            ->whereNull('send_id')
            ->count();

        return $messages + $withoutSend;
    }
}
