<?php

declare(strict_types=1);

namespace J87NL\Mailing\Mail;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class TransportFailure
{
    private const array AUTHENTICATION_CODES = [
        530,
        535,
    ];

    public static function describe(Throwable $exception): string
    {
        if (! $exception instanceof TransportExceptionInterface) {
            return (string) __('mailing::campaigns.transport_errors.unknown');
        }

        $code = (int) $exception->getCode();

        if (in_array($code, self::AUTHENTICATION_CODES, true)) {
            return (string) __('mailing::campaigns.transport_errors.authentication');
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

        return self::describeMessage(strtolower($exception->getMessage()));
    }

    private static function describeMessage(string $message): string
    {
        if (str_contains($message, 'authenticat')) {
            return (string) __('mailing::campaigns.transport_errors.authentication');
        }

        if (str_contains($message, 'timed out') || str_contains($message, 'timeout')) {
            return (string) __('mailing::campaigns.transport_errors.timeout');
        }

        if (str_contains($message, 'connection could not be established') || str_contains($message, 'connection refused')) {
            return (string) __('mailing::campaigns.transport_errors.connection');
        }

        return (string) __('mailing::campaigns.transport_errors.unknown');
    }
}
