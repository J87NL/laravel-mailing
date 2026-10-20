<?php

declare(strict_types=1);

namespace J87NL\Mailing\Tests\Fakes;

use J87NL\Mailing\Contracts\MailboxReader;
use J87NL\Mailing\Mailbox\MailboxMessage;

class FakeMailboxReader implements MailboxReader
{
    /**
     * @var list<MailboxMessage>
     */
    public array $messages = [];

    /**
     * @var list<string>
     */
    public array $archived = [];

    public function unprocessed(): iterable
    {
        return array_values(array_filter(
            $this->messages,
            fn (MailboxMessage $message): bool => ! in_array($message->id, $this->archived, true),
        ));
    }

    public function archive(MailboxMessage $message): void
    {
        $this->archived[] = $message->id;
    }
}
