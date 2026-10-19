<?php

declare(strict_types=1);

namespace J87NL\Mailing\Mailbox;

final readonly class MailboxMessage
{
    public function __construct(
        public string $id,
        public string $raw,
    ) {}
}
