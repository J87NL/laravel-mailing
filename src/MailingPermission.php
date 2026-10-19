<?php

declare(strict_types=1);

namespace J87NL\Mailing;

enum MailingPermission: string
{
    case Lists = 'mailing.lists';
    case Subscribers = 'mailing.subscribers';
    case Settings = 'mailing.settings';

    public function label(): string
    {
        return __(sprintf('mailing::permissions.%s', $this->value));
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_map(
            fn (self $permission): string => $permission->value,
            self::cases(),
        );
    }
}
