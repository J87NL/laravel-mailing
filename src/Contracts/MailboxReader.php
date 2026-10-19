<?php

declare(strict_types=1);

namespace J87NL\Mailing\Contracts;

use J87NL\Mailing\Mailbox\MailboxMessage;

interface MailboxReader
{
    /**
     * @return iterable<MailboxMessage>
     */
    public function unprocessed(): iterable;

    public function archive(MailboxMessage $message): void;
}
