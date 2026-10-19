<?php

declare(strict_types=1);

namespace J87NL\Mailing\Enums;

enum SubscriptionStatus: string
{
    case Pending = 'pending';
    case Subscribed = 'subscribed';
    case Unsubscribed = 'unsubscribed';
    case Bounced = 'bounced';
    case Complained = 'complained';

    public function label(): string
    {
        return __(sprintf('mailing::enums.subscription_status.%s', $this->value));
    }
}
