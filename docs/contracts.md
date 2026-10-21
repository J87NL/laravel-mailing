# Contracts

The package reaches the outside world through a few interfaces in `J87NL\Mailing\Contracts`. You
replace one by changing a config key. All but `MailingDriver` are bound as singletons to the class
named in the config. Drivers are not bound in the container: `DriverRegistry::find()` resolves the
configured class anew on every call.

| Contract                 | Config key                         | Default                        |
| ------------------------ | ---------------------------------- | ------------------------------ |
| `SenderIdentityProvider` | `mailing.sender_identity_provider` | `ConfigSenderIdentityProvider` |
| `MailboxReader`          | `mailing.mailbox_reader`           | `NullMailboxReader`            |
| `HtmlSanitizer`          | `mailing.html_sanitizer`           | `BasicHtmlSanitizer`           |
| `DnsResolver`            | `mailing.preflight.dns_resolver`   | `NativeDnsResolver`            |
| `SpamScorer`             | `mailing.preflight.spam_scorer`    | `RspamdSpamScorer`             |
| `PostfixLogReader`       | `mailing.postfix_log.reader`       | `FilePostfixLogReader`         |
| `MailingDriver`          | `mailing.drivers.<key>.class`      | `PostfixDriver`, `LettermintDriver` |

All classes are resolved from the container, so they can have constructor dependencies.

## SenderIdentityProvider

Supplies the From identities a list can choose from.

```php
interface SenderIdentityProvider
{
    /**
     * @return array<string, SenderIdentity>
     */
    public function identities(): array;

    public function find(string $key): ?SenderIdentity;
}
```

`J87NL\Mailing\Senders\SenderIdentity` has `key`, `email` and an optional `name`, a `label()`
("Name <email>") and an `address()` that returns a mail `Address`. The first identity is the
fallback for lists without a valid one.

```php
<?php

declare(strict_types=1);

namespace App\Mailing;

use App\Models\Site;
use J87NL\Mailing\Contracts\SenderIdentityProvider;
use J87NL\Mailing\Senders\SenderIdentity;

class SiteSenderIdentityProvider implements SenderIdentityProvider
{
    public function identities(): array
    {
        $identities = [];

        foreach (Site::query()->get() as $site) {
            $identities[$site->slug] = new SenderIdentity($site->slug, $site->email, $site->name);
        }

        return $identities;
    }

    public function find(string $key): ?SenderIdentity
    {
        return $this->identities()[$key] ?? null;
    }
}
```

## MailboxReader

Gives the bounce command access to the bounce mailbox. See [Bounces](delivery/bounces.md#the-bounce-mailbox).

```php
interface MailboxReader
{
    /**
     * @return iterable<MailboxMessage>
     */
    public function unprocessed(): iterable;

    public function archive(MailboxMessage $message): void;
}
```

`unprocessed()` returns the messages to look at, as `new MailboxMessage($id, $raw)`. `archive()` is
called for every message after it was looked at, applied or not, so it is not returned again.

## HtmlSanitizer

Cleans the campaign body on save, on render and before the link and text checks.

```php
interface HtmlSanitizer
{
    public function sanitize(?string $html): string;
}
```

The default `BasicHtmlSanitizer` allows a small set of text elements and links, see
[Composing campaigns](campaigns/composing.md#sanitizing). A replacement must return safe HTML for
mail clients; the result is printed unescaped in the mail.

## DnsResolver

DNS lookups for the pre-flight checks.

```php
interface DnsResolver
{
    /**
     * @return list<string>
     */
    public function txt(string $host): array;

    /**
     * @return list<string>
     */
    public function a(string $host): array;
}
```

`txt()` returns the TXT records of a host as strings, `a()` the IPv4 addresses. Both return an
empty list when there is nothing. The default `NativeDnsResolver` uses `dns_get_record()`.

## SpamScorer

The spam score for the pre-flight check.

```php
interface SpamScorer
{
    public function score(string $rawMessage): ?float;
}
```

It gets the full rendered message and returns a score, or `null` when none is available. `null`
makes the check orange. Besides `RspamdSpamScorer` the package ships `NullSpamScorer`, which always
returns `null`.

## PostfixLogReader

Supplies the Postfix log lines that are new since the previous call. `mailing:read-postfix-log` only
calls it when `mailing.postfix_log.path` is set.

```php
interface PostfixLogReader
{
    /**
     * @return iterable<string>
     */
    public function lines(): iterable;
}
```

The default, `J87NL\Mailing\Postfix\FilePostfixLogReader`, reads `mailing.postfix_log.path` from
the stored position on and follows log rotation. See [Postfix delivery log](delivery/postfix.md).

## MailingDriver

Transport-specific marking and webhooks. See [Drivers](delivery/drivers.md#writing-your-own-driver).
