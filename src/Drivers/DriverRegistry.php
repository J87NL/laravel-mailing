<?php

declare(strict_types=1);

namespace J87NL\Mailing\Drivers;

use Illuminate\Contracts\Container\Container;
use J87NL\Mailing\Contracts\MailingDriver;
use J87NL\Mailing\Models\MailingList;

class DriverRegistry
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function for(MailingList $list): MailingDriver
    {
        $map = config('mailing.mailer_drivers', []);
        $key = is_array($map) && is_string($map[(string) $list->mailer] ?? null) ? $map[(string) $list->mailer] : 'postfix';

        return $this->find($key) ?? $this->container->make(PostfixDriver::class);
    }

    public function find(string $key): ?MailingDriver
    {
        $drivers = config('mailing.drivers', []);
        $class = is_array($drivers) && is_array($drivers[$key] ?? null) ? ($drivers[$key]['class'] ?? null) : null;

        if (! is_string($class) || ! class_exists($class)) {
            return null;
        }

        $driver = $this->container->make($class);

        return $driver instanceof MailingDriver ? $driver : null;
    }
}
