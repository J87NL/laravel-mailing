<?php

declare(strict_types=1);

namespace J87NL\Mailing\Enums;

enum SuppressionReason: string
{
    case HardBounce = 'hard_bounce';
    case Complaint = 'complaint';
    case Manual = 'manual';
    case UnsubscribeAll = 'unsubscribe_all';

    public function label(): string
    {
        return __(sprintf('mailing::enums.suppression_reason.%s', $this->value));
    }
}
