<?php

declare(strict_types=1);

namespace J87NL\Mailing\Enums;

enum ConsentAction: string
{
    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Imported = 'imported';
    case Unsubscribed = 'unsubscribed';
    case Suppressed = 'suppressed';
    case Unsuppressed = 'unsuppressed';

    public function label(): string
    {
        return __(sprintf('mailing::enums.consent_action.%s', $this->value));
    }
}
