<?php

declare(strict_types=1);

namespace J87NL\Mailing\Mail;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class TransportFailure
{
    public static function describe(TransportExceptionInterface $exception): string
    {
        $message = strtolower($exception->getMessage());
        $code = (int) $exception->getCode();

        if ($code === 535 || str_contains($message, 'authenticat')) {
            return (string) __('mailing::campaigns.transport_errors.authentication');
        }

        if (str_contains($message, 'timed out') || str_contains($message, 'timeout')) {
            return (string) __('mailing::campaigns.transport_errors.timeout');
        }

        if (str_contains($message, 'connection could not be established') || str_contains($message, 'connection refused')) {
            return (string) __('mailing::campaigns.transport_errors.connection');
        }

        if ($code >= 500 && $code < 600) {
            return (string) __('mailing::campaigns.transport_errors.rejected', [
                'code' => $code,
            ]);
        }

        if ($code >= 400 && $code < 500) {
            return (string) __('mailing::campaigns.transport_errors.deferred', [
                'code' => $code,
            ]);
        }

        return (string) __('mailing::campaigns.transport_errors.unknown');
    }
}
