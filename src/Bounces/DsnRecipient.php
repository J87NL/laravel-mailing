<?php

declare(strict_types=1);

namespace J87NL\Mailing\Bounces;

final readonly class DsnRecipient
{
    public function __construct(
        public string $recipient,
        public string $action,
        public string $status,
        public ?string $diagnostic,
    ) {}

    public function isHard(): bool
    {
        return $this->action === 'failed' && str_starts_with($this->status, '5.');
    }

    public function isSoft(): bool
    {
        return $this->action === 'failed' && str_starts_with($this->status, '4.');
    }

    public function isDelay(): bool
    {
        return $this->action === 'delayed';
    }
}
