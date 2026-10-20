<?php

declare(strict_types=1);

namespace J87NL\Mailing\Enums;

enum CampaignStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Sending = 'sending';
    case Paused = 'paused';
    case Sent = 'sent';
    case Cancelled = 'cancelled';

    public function allowsRetry(): bool
    {
        return in_array($this, [
            self::Sending,
            self::Paused,
            self::Sent,
        ], true);
    }

    public function label(): string
    {
        return __(sprintf('mailing::enums.campaign_status.%s', $this->value));
    }
}
