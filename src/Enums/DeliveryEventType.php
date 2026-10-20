<?php

declare(strict_types=1);

namespace J87NL\Mailing\Enums;

enum DeliveryEventType: string
{
    case Delivered = 'delivered';
    case Deferred = 'deferred';
    case HardBounce = 'hard_bounce';
    case SoftBounce = 'soft_bounce';
    case Complained = 'complained';
    case Failed = 'failed';
    case Unsubscribed = 'unsubscribed';
    case SuppressionAdded = 'suppression_added';
    case SuppressionRemoved = 'suppression_removed';
}
