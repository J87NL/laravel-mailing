<?php

declare(strict_types=1);

namespace J87NL\Mailing\Tests\Fakes;

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

class RejectingWith554Transport extends AbstractTransport
{
    protected function doSend(SentMessage $message): void
    {
        throw new TransportException('Expected response code "250" but got code "554", with message "554 5.7.1 Message rejected".', 554);
    }

    public function __toString(): string
    {
        return 'rejecting-554://';
    }
}
