<?php

declare(strict_types=1);

namespace J87NL\Mailing\Actions;

use Illuminate\Support\Facades\DB;
use J87NL\Mailing\Enums\ConsentAction;
use J87NL\Mailing\Enums\SubscriptionStatus;
use J87NL\Mailing\Models\MailingList;
use J87NL\Mailing\Models\Subscriber;
use J87NL\Mailing\Models\Subscription;
use J87NL\Mailing\Models\Suppression;
use J87NL\Mailing\Support\EmailAddress;

class RequestSubscription
{
    public function __construct(
        private readonly RecordConsent $recordConsent,
        private readonly SendConfirmation $sendConfirmation,
    ) {}

    public function handle(MailingList $list, string $email, ?string $name, ?string $ip, string $source, ?string $consentText = null): ?Subscription
    {
        $email = EmailAddress::normalize($email);

        if (Suppression::covers($email)) {
            return null;
        }

        $alreadySent = false;

        $subscription = DB::transaction(function () use ($list, $email, $name, $ip, $source, $consentText, &$alreadySent): ?Subscription {
            $subscriber = Subscriber::query()
                ->lockForUpdate()
                ->firstOrCreate([
                    'email' => $email,
                ]);

            $name = trim((string) $name);

            $subscription = Subscription::query()
                ->lockForUpdate()
                ->firstOrNew([
                    'subscriber_id' => $subscriber->id,
                    'list_id' => $list->id,
                ]);

            if ($subscription->exists && $subscription->status === SubscriptionStatus::Subscribed) {
                return null;
            }

            if ($this->recentlySent($subscription)) {
                $alreadySent = true;

                return $subscription;
            }

            $subscription->fill([
                'status' => SubscriptionStatus::Pending,
                'consent_at' => null,
                'consent_ip' => $ip,
                'consent_source' => $source,
                'requested_name' => $name === '' ? null : $name,
                'confirmation_sent_at' => now(),
                'confirmed_at' => null,
                'unsubscribed_at' => null,
            ])->save();

            $this->recordConsent->handle($subscriber, $list, ConsentAction::Requested, $source, $ip, $consentText);

            return $subscription;
        }, 3);

        if (is_null($subscription) || $alreadySent) {
            return $subscription;
        }

        $this->sendConfirmation->handle($subscription);

        return $subscription;
    }

    private function recentlySent(Subscription $subscription): bool
    {
        if (! $subscription->exists || $subscription->status !== SubscriptionStatus::Pending || is_null($subscription->confirmation_sent_at)) {
            return false;
        }

        return $subscription->confirmation_sent_at->gt(now()->subHours((int) config('mailing.confirmation.resend_after_hours', 24)));
    }
}
