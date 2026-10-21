<?php

declare(strict_types=1);

namespace J87NL\Mailing\Mail;

use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class TransportFailure
{
    private const array SMTP_AUTHENTICATION_CODES = [
        530,
        535,
    ];

    private const array HTTP_AUTHENTICATION_STATUSES = [
        401,
        403,
    ];

    private const int HTTP_TIMEOUT_STATUS = 408;

    private const int HTTP_TOO_MANY_REQUESTS_STATUS = 429;

    public static function describe(Throwable $exception): string
    {
        if (! $exception instanceof TransportExceptionInterface) {
            return (string) __('mailing::campaigns.transport_errors.unknown');
        }

        $code = (int) $exception->getCode();

        if ($code < 400 || $code >= 600) {
            return self::describeMessage(strtolower($exception->getMessage()));
        }

        if (self::carriesHttpStatus($exception)) {
            return self::describeHttpStatus($code);
        }

        return self::describeSmtpCode($code);
    }

    private static function carriesHttpStatus(TransportExceptionInterface $exception): bool
    {
        if ($exception instanceof HttpTransportException) {
            return true;
        }

        $previous = $exception->getPrevious();

        return ! is_null($previous) && ! $previous instanceof TransportExceptionInterface;
    }

    private static function describeHttpStatus(int $status): string
    {
        if (in_array($status, self::HTTP_AUTHENTICATION_STATUSES, true)) {
            return (string) __('mailing::campaigns.transport_errors.authentication');
        }

        if ($status === self::HTTP_TIMEOUT_STATUS) {
            return (string) __('mailing::campaigns.transport_errors.timeout');
        }

        if ($status === self::HTTP_TOO_MANY_REQUESTS_STATUS) {
            return (string) __('mailing::campaigns.transport_errors.deferred', [
                'code' => $status,
            ]);
        }

        if ($status >= 500) {
            return (string) __('mailing::campaigns.transport_errors.unavailable', [
                'code' => $status,
            ]);
        }

        return (string) __('mailing::campaigns.transport_errors.rejected', [
            'code' => $status,
        ]);
    }

    private static function describeSmtpCode(int $code): string
    {
        if (in_array($code, self::SMTP_AUTHENTICATION_CODES, true)) {
            return (string) __('mailing::campaigns.transport_errors.authentication');
        }

        if ($code >= 500) {
            return (string) __('mailing::campaigns.transport_errors.rejected', [
                'code' => $code,
            ]);
        }

        return (string) __('mailing::campaigns.transport_errors.deferred', [
            'code' => $code,
        ]);
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
