<?php

declare(strict_types=1);

namespace J87NL\Mailing\Tests\Fakes;

use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

class ApiFailureTransport extends AbstractTransport
{
    public function __construct(private readonly int $status)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $apiError = new RuntimeException('The API answered with an error.', $this->status);

        throw new TransportException(sprintf('Sending email via the API failed: %s', $apiError->getMessage()), $this->status, $apiError);
    }

    public function __toString(): string
    {
        return 'api-failure://';
    }
}
