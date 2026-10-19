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

    public function handle(MailingList $list, string $email, ?string $name, ?string $ip, string $source): ?Subscription
    {
        $email = EmailAddress::normalize($email);

        if (Suppression::covers($email)) {
            return null;
        }

        $subscription = DB::transaction(function () use ($list, $email, $name, $ip, $source): ?Subscription {
            $subscriber = Subscriber::query()
                ->lockForUpdate()
                ->firstOrCreate([
                    'email' => $email,
                ]);

            $name = trim((string) $name);

            $subscription = Subscription::query()
                ->firstOrNew([
                    'subscriber_id' => $subscriber->id,
                    'list_id' => $list->id,
                ]);

            if ($subscription->exists && $subscription->status === SubscriptionStatus::Subscribed) {
                return null;
            }

            $subscription->fill([
                'status' => SubscriptionStatus::Pending,
                'consent_at' => null,
                'consent_ip' => $ip,
                'consent_source' => $source,
                'requested_name' => $name === '' ? null : $name,
                'confirmed_at' => null,
                'unsubscribed_at' => null,
            ])->save();

            $this->recordConsent->handle($subscriber, $list, ConsentAction::Requested, $source, $ip);

            return $subscription;
        });

        if (is_null($subscription)) {
            return null;
        }

        $this->sendConfirmation->handle($subscription);

        return $subscription;
    }
}
