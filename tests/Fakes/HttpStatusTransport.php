<?php

declare(strict_types=1);

namespace J87NL\Mailing\Tests\Fakes;

use Exception;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

class HttpStatusTransport extends AbstractTransport
{
    public function __construct(private readonly int $status)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $exception = (new ReflectionClass(HttpTransportException::class))->newInstanceWithoutConstructor();

        (new ReflectionProperty(Exception::class, 'message'))->setValue($exception, sprintf('Unable to send an email (code %d).', $this->status));
        (new ReflectionProperty(Exception::class, 'code'))->setValue($exception, $this->status);

        throw $exception;
    }

    public function __toString(): string
    {
        return 'http-status://';
    }
}
