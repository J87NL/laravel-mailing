<?php

declare(strict_types=1);

namespace J87NL\Mailing\Tests\Fakes;

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

class FailingTransport extends AbstractTransport
{
    public static int $code = 450;

    protected function doSend(SentMessage $message): void
    {
        throw new TransportException(sprintf('Expected response code "250" but got code "%d", with message "%d 4.7.1 Try again later".', self::$code, self::$code), self::$code);
    }

    public function __toString(): string
    {
        return 'failing://';
    }
}
