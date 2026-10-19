<?php

declare(strict_types=1);

namespace J87NL\Mailing\Actions;

use Illuminate\Support\Facades\DB;
use J87NL\Mailing\Enums\ConsentAction;
use J87NL\Mailing\Enums\SubscriptionStatus;
use J87NL\Mailing\Enums\SuppressionReason;
use J87NL\Mailing\Models\Subscriber;
use J87NL\Mailing\Models\Suppression;
use J87NL\Mailing\Support\EmailAddress;

class SuppressAddress
{
    public function __construct(
        private readonly Unsubscribe $unsubscribe,
        private readonly RecordConsent $recordConsent,
    ) {}

    public function handle(
        string $email,
        SuppressionReason $reason,
        string $source,
        ?string $note = null,
        ?int $actorId = null,
        ?string $ip = null,
    ): Suppression {
        $email = EmailAddress::normalize($email);

        return DB::transaction(function () use ($email, $reason, $source, $note, $actorId, $ip): Suppression {
            $suppression = Suppression::query()
                ->firstOrCreate([
                    'email' => $email,
                ], [
                    'reason' => $reason,
                    'source' => $source,
                    'note' => $note,
                    'created_by' => $actorId,
                ]);

            $subscriber = Subscriber::query()
                ->where('email', $email)
                ->first();

            if (is_null($subscriber)) {
                return $suppression;
            }

            foreach ($subscriber->subscriptions()->get() as $subscription) {
                $this->unsubscribe->handle($subscription, $source, $ip, $actorId, $this->statusFor($reason));
            }

            if ($suppression->wasRecentlyCreated) {
                $this->recordConsent->handle($subscriber, null, ConsentAction::Suppressed, $source, $ip, $note, $actorId);
            }

            return $suppression;
        });
    }

    private function statusFor(SuppressionReason $reason): SubscriptionStatus
    {
        return match ($reason) {
            SuppressionReason::HardBounce => SubscriptionStatus::Bounced,
            SuppressionReason::Complaint => SubscriptionStatus::Complained,
            SuppressionReason::Manual, SuppressionReason::UnsubscribeAll => SubscriptionStatus::Unsubscribed,
        };
    }
}
