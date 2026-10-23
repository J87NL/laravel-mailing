<?php

declare(strict_types=1);

namespace J87NL\Mailing\Tests\Fakes;

use J87NL\Mailing\Contracts\BatchArchivingMailboxReader;
use J87NL\Mailing\Mailbox\MailboxMessage;
use LogicException;

class FakeBatchMailboxReader extends FakeMailboxReader implements BatchArchivingMailboxReader
{
    /**
     * @var list<list<string>>
     */
    public array $batches = [];

    public function archive(MailboxMessage $message): void
    {
        throw new LogicException(sprintf('Message %s was archived on its own.', $message->id));
    }

    public function archiveMany(array $messages): void
    {
        $ids = array_map(fn (MailboxMessage $message): string => $message->id, $messages);

        $this->batches[] = $ids;
        $this->archived = [...$this->archived, ...$ids];
    }
}
