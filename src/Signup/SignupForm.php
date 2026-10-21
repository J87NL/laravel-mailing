<?php

declare(strict_types=1);

namespace J87NL\Mailing\Signup;

use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use J87NL\Mailing\Models\MailingList;

final readonly class SignupForm
{
    public function __construct(
        private SignupGuard $guard,
    ) {}

    /**
     * @param  array<string, string>  $old
     * @return array<string, mixed>
     */
    public function data(
        MailingList $list,
        Request $request,
        ?string $returnTo = null,
        ?MessageBag $errors = null,
        array $old = [],
        string $title = '',
        string $intro = '',
        string $buttonLabel = '',
        bool $resubmission = false,
    ): array {
        return [
            'list' => $list,
            'anchor' => self::anchor($list),
            'action' => route('mailing.subscribe.store', $list),
            'token' => $this->guard->token($list->id, $resubmission),
            'returnTo' => $returnTo,
            'title' => $title,
            'intro' => $intro,
            'buttonLabel' => $buttonLabel !== '' ? $buttonLabel : __('mailing::signup.button'),
            'subscribed' => self::succeeded($request, $list),
            'errors' => $errors ?? new MessageBag,
            'old' => $old,
            'privacyUrl' => self::privacyUrl(),
        ];
    }

    public static function privacyUrl(): ?string
    {
        $url = config('mailing.signup.privacy_url');

        if (! is_string($url)) {
            return null;
        }

        $url = trim($url);

        if (preg_match('#^/(?![/\\\\])\S*$#', $url) === 1) {
            return $url;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = (string) parse_url($url, PHP_URL_HOST);

        $schemes = [
            'http',
            'https',
        ];

        if (! in_array($scheme, $schemes, true) || $host === '' || preg_match('/\s/', $url) === 1) {
            return null;
        }

        return $url;
    }

    public static function anchor(MailingList $list): string
    {
        return sprintf('mailing-%s', $list->key);
    }

    public static function succeeded(Request $request, MailingList $list): bool
    {
        return (string) $request->query(self::successQuery(), '') === $list->key;
    }

    public static function successUrl(MailingList $list, ?string $returnTo): string
    {
        $base = self::isLocalPath($returnTo) ? (string) $returnTo : route('mailing.subscribe.show', $list, false);

        return sprintf('%s?%s=%s#%s', $base, self::successQuery(), rawurlencode($list->key), self::anchor($list));
    }

    public static function successQuery(): string
    {
        $query = config('mailing.signup.success_query');

        return is_string($query) && $query !== '' ? $query : 'aangemeld';
    }

    public static function isLocalPath(?string $path): bool
    {
        if (is_null($path) || strlen($path) > 2048) {
            return false;
        }

        return preg_match('#^/(?![/\\\\])[^\s?\#]*$#', $path) === 1;
    }
}
