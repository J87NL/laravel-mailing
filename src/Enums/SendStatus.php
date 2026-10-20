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

    public function rank(): int
    {
        return match ($this) {
            self::Bounced, self::Complained => 5,
            self::Delivered => 4,
            self::Failed => 3,
            self::Deferred => 2,
            self::HandedOver => 1,
            self::Queued, self::Sending, self::Skipped, self::Cancelled => 0,
        };
    }

    /**
     * @return list<self>
     */
    public function replaces(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $status): bool => $status->rank() <= $this->rank(),
        ));
    }

    public function label(): string
    {
        return __(sprintf('mailing::enums.send_status.%s', $this->value));
    }
}
