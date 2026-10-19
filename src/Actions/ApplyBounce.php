<?php

declare(strict_types=1);

namespace J87NL\Mailing\Actions;

use J87NL\Mailing\Enums\BounceType;
use J87NL\Mailing\Enums\SendStatus;
use J87NL\Mailing\Enums\SuppressionReason;
use J87NL\Mailing\Models\BounceEvent;
use J87NL\Mailing\Models\Send;

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

        if (! is_null($send)) {
            $send->forceFill([
                'status' => $type === BounceType::Hard ? SendStatus::Bounced : SendStatus::Deferred,
                'next_attempt_at' => null,
                'bounced_at' => now(),
                'bounce_type' => $type,
                'bounce_diagnostic' => $diagnostic,
            ])->save();
        }

        if ($type === BounceType::Hard) {
            $this->suppressAddress->handle($email, SuppressionReason::HardBounce, $source, $diagnostic);

            return;
        }

        $softBounces = BounceEvent::query()
            ->where('email', $email)
            ->where('type', BounceType::Soft)
            ->where('created_at', '>=', now()->subDays((int) config('mailing.bounces.soft_window_days', 30)))
            ->count();

        if ($softBounces < (int) config('mailing.bounces.soft_limit', 3)) {
            return;
        }

        if (! is_null($send)) {
            $send->forceFill([
                'status' => SendStatus::Bounced,
            ])->save();
        }

        $this->suppressAddress->handle($email, SuppressionReason::SoftBounces, $source, $diagnostic);
    }
}
