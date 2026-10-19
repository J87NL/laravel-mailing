<?php

declare(strict_types=1);

namespace J87NL\Mailing\Support;

final class EmailAddress
{
    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function domain(string $email): string
    {
        $position = strrpos($email, '@');

        if ($position === false) {
            return '';
        }

        return mb_strtolower(substr($email, $position + 1));
    }
}
