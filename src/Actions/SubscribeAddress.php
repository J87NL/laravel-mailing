<?php

declare(strict_types=1);

namespace J87NL\Mailing\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use J87NL\Mailing\Enums\ConsentAction;
use J87NL\Mailing\Enums\SubscriptionStatus;
use J87NL\Mailing\Models\MailingList;
use J87NL\Mailing\Models\Subscriber;
use J87NL\Mailing\Models\Subscription;
use J87NL\Mailing\Models\Suppression;
use J87NL\Mailing\Support\ConsentSource;
use J87NL\Mailing\Support\EmailAddress;

class SubscribeAddress
{
    private const int MAX_NAME_LENGTH = 120;

    public function __construct(
        private readonly RecordConsent $recordConsent,
    ) {}

    /**
     * @throws InvalidArgumentException
     */
    public function handle(
        MailingList $list,
        string $email,
        string $consentSource,
        ?string $consentNote = null,
        ?int $actorId = null,
        ?string $name = null,
    ): ?Subscription {
        $email = EmailAddress::normalize($email);

        if (! EmailAddress::isValid($email)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid email address.', $email));
        }

        if (trim($consentSource) === '') {
            throw new InvalidArgumentException('A direct subscription needs a consent source.');
        }

        if (Suppression::covers($email)) {
            return null;
        }

        $source = ConsentSource::make(ConsentSource::HOST, $consentSource);
        $consentNote = trim((string) $consentNote);
        $name = mb_substr(trim((string) $name), 0, self::MAX_NAME_LENGTH);

        try {
            return $this->subscribe($list, $email, $source, $consentNote, $actorId, $name);
        } catch (UniqueConstraintViolationException) {
            return $this->subscribe($list, $email, $source, $consentNote, $actorId, $name);
        }
    }

    private function subscribe(MailingList $list, string $email, string $source, string $consentNote, ?int $actorId, string $name): ?Subscription
    {
        return DB::transaction(function () use ($list, $email, $source, $consentNote, $actorId, $name): ?Subscription {
            $subscriber = Subscriber::query()
                ->lockForUpdate()
                ->firstOrCreate([
                    'email' => $email,
                ]);

            if (Suppression::covers($email)) {
                return null;
            }

            $subscription = Subscription::query()
                ->lockForUpdate()
                ->firstOrNew([
                    'subscriber_id' => $subscriber->id,
                    'list_id' => $list->id,
                ]);

            if ($subscription->exists && $subscription->status === SubscriptionStatus::Subscribed) {
                return $subscription;
            }

            if ($subscription->exists && $subscription->status !== SubscriptionStatus::Pending) {
                return null;
            }

            $subscription->forceFill([
                'status' => SubscriptionStatus::Subscribed,
                'consent_at' => now(),
                'consent_ip' => null,
                'consent_source' => $source,
                'requested_name' => null,
                'confirmed_at' => now(),
            ])->save();

            $this->adoptName($subscriber, $name);

            $this->recordConsent->handle($subscriber, $list, ConsentAction::Confirmed, $source, null, $consentNote === '' ? null : $consentNote, $actorId);

            return $subscription;
        }, 3);
    }

    private function adoptName(Subscriber $subscriber, string $name): void
    {
        if ($name === '' || (! is_null($subscriber->name) && $subscriber->name !== '')) {
            return;
        }

        $subscriber->name = $name;
        $subscriber->save();
    }
}
