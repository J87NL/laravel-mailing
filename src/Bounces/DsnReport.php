<?php

declare(strict_types=1);

namespace J87NL\Mailing\Bounces;

final readonly class DsnReport
{
    /**
     * @param  list<DsnRecipient>  $recipients
     */
    public function __construct(
        public array $recipients,
        public ?int $verpSendId,
        public ?string $originalMessageId,
    ) {}
}
