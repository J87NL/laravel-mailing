<?php

declare(strict_types=1);

namespace J87NL\Mailing\Actions;

use J87NL\Mailing\Models\MailingList;
use J87NL\Mailing\Models\Subscriber;
use J87NL\Mailing\Support\ConsentSource;
use J87NL\Mailing\Support\EmailAddress;

class UnsubscribeAddress
{
    public function __construct(
        private readonly Unsubscribe $unsubscribe,
    ) {}

    public function handle(MailingList $list, string $email, string $source, ?int $actorId = null): bool
    {
        $subscriber = Subscriber::query()
            ->where('email', EmailAddress::normalize($email))
            ->first();

        $subscription = $subscriber?->subscriptions()
            ->where('list_id', $list->id)
            ->first();

        if (is_null($subscription)) {
            return false;
        }

        return $this->unsubscribe->handle($subscription, ConsentSource::make(ConsentSource::HOST, $source), null, $actorId);
    }
}
