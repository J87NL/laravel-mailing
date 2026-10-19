<?php

declare(strict_types=1);

namespace J87NL\Mailing\Mailbox;

use J87NL\Mailing\Contracts\MailboxReader;

final class NullMailboxReader implements MailboxReader
{
    public function unprocessed(): iterable
    {
        return [];
    }

    public function archive(MailboxMessage $message): void {}
}
