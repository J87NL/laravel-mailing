<?php

declare(strict_types=1);

namespace J87NL\Mailing\Tests\Fakes;

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

class RecipientRejectingTransport extends AbstractTransport
{
    public const string REJECTED = 'weigeraar@example.org';

    /**
     * @var list<string>
     */
    public static array $delivered = [];

    protected function doSend(SentMessage $message): void
    {
        $recipient = $message->getEnvelope()->getRecipients()[0]->getAddress();

        if ($recipient === self::REJECTED) {
            throw new TransportException('Expected response code "250" but got code "550", with message "550 5.7.1 Unauthenticated sender not allowed".', 550);
        }

        self::$delivered[] = $recipient;
    }

    public function __toString(): string
    {
        return 'rejecting://';
    }
}
