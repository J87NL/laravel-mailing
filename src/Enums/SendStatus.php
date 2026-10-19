<?php

declare(strict_types=1);

namespace J87NL\Mailing\Enums;

enum SendStatus: string
{
    case Queued = 'queued';
    case Sending = 'sending';
    case HandedOver = 'handed_over';
    case Deferred = 'deferred';
    case Delivered = 'delivered';
    case Bounced = 'bounced';
    case Complained = 'complained';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __(sprintf('mailing::enums.send_status.%s', $this->value));
    }
}
