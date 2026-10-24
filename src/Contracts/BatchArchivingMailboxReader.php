<?php

declare(strict_types=1);

namespace J87NL\Mailing\Contracts;

use J87NL\Mailing\Mailbox\MailboxMessage;

interface BatchArchivingMailboxReader extends MailboxReader
{
    /**
     * @param  list<MailboxMessage>  $messages
     */
    public function archiveMany(array $messages): void;
}
