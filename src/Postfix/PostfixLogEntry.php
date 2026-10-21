<?php

declare(strict_types=1);

namespace J87NL\Mailing\Postfix;

final readonly class PostfixLogEntry
{
    public function __construct(
        public PostfixLogEntryType $type,
        public string $queueId,
        public ?string $messageId = null,
        public ?string $recipient = null,
        public ?string $originalRecipient = null,
        public ?string $status = null,
        public ?string $dsn = null,
        public ?string $reply = null,
    ) {}
}
