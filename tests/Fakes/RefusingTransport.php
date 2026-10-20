<?php

declare(strict_types=1);

namespace J87NL\Mailing\Tests\Fakes;

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

class RefusingTransport extends AbstractTransport
{
    protected function doSend(SentMessage $message): void
    {
        throw new TransportException('Connection could not be established with host "127.0.0.1:25": stream_socket_client(): Unable to connect to 127.0.0.1:25 (Connection refused)');
    }

    public function __toString(): string
    {
        return 'refusing://';
    }
}
