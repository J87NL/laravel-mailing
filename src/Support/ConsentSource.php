<?php

declare(strict_types=1);

namespace J87NL\Mailing\Support;

use Illuminate\Support\Str;

class ConsentSource
{
    public const string SIGNUP = 'signup';

    public const string IMPORT = 'import';

    public const string ADMIN = 'admin';

    public const string ONE_CLICK = 'one-click';

    public const string UNSUBSCRIBE_PAGE = 'unsubscribe-page';

    public const string DSN = 'dsn';

    public const string WEBHOOK = 'webhook';

    private const int MAX_DETAIL_LENGTH = 200;

    private const string SEPARATOR = ': ';

    public static function make(string $key, ?string $detail = null): string
    {
        $detail = trim((string) $detail);

        if ($detail === '') {
            return $key;
        }

        return sprintf('%s%s%s', $key, self::SEPARATOR, Str::limit($detail, self::MAX_DETAIL_LENGTH));
    }

    public static function describe(?string $source): string
    {
        if (is_null($source) || $source === '') {
            return '';
        }

        [$key, $detail] = array_pad(explode(self::SEPARATOR, $source, 2), 2, '');
        $translationKey = sprintf('mailing::common.sources.%s', $key);

        if (! trans()->has($translationKey)) {
            return $source;
        }

        return (string) __($translationKey, [
            'detail' => $detail,
        ]);
    }
}
