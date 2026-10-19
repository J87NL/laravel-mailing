# j87nl/laravel-mailing

Mailing lists for Laravel: double opt-in sign-up, consent records, RFC 8058 one-click unsubscribe, a global suppression list and CSV imports with a required consent source. The public pages set no cookies and load no JavaScript; the admin is Inertia and Vue on TranquilTools forms and tables.

Campaigns and sending arrive in a later release.

## Requirements

- PHP 8.5 and Laravel 13
- Inertia 3 with the TranquilTools form and table builders for the admin
- Optionally `j87nl/laravel-cms`, which gets a sign-up block

## Installation

```bash
composer require j87nl/laravel-mailing
php artisan vendor:publish --tag=mailing-config
php artisan migrate
```

Point `mailing.layout` at your public Blade layout (it yields `title`, `head` and `content`) and add the package pages to your Inertia resolver under the `mailing/` prefix, with a Vite alias `@mailing` to `resources/js` of this package.

## What it does

- **Sign-up:** a standalone page `/mailing/{list}/subscribe` and, when the CMS is installed, a `mailing_signup` block. Both are protected by a honeypot, a signed timestamp and rate limits per IP address, per address and overall, instead of CSRF and a session. The response is identical whether the address is new, pending, subscribed or suppressed.
- **Double opt-in:** a confirmation mail with a signed link valid for 7 days. The link opens a page with a button; only the POST confirms, so link scanners cannot confirm on someone's behalf.
- **Unsubscribe:** every subscription has its own token. A POST to the unsubscribe URL unsubscribes at once (RFC 8058 one-click); a GET only shows a page with "this list" and "all lists". "All lists" adds a suppression.
- **List headers:** mailables extending `ListMailable` carry `List-Unsubscribe` (HTTPS and, when configured, `mailto:`), `List-Unsubscribe-Post` and `List-Id`.
- **Consent records:** every request, confirmation, import, unsubscribe and suppression is recorded with source, IP address, note and the acting admin.
- **Admin:** lists, the transport per list, subscribers with their history and a CSV export, manual unsubscribe, suppressions and imports.
- **Retention:** `mailing:prune` runs daily and deletes unconfirmed sign-ups after 30 days and the consent records of unsubscribed addresses after 2 years. Suppressions stay.

## Contracts

| Contract | Default | Purpose |
| --- | --- | --- |
| `SenderIdentityProvider` | `ConfigSenderIdentityProvider` (`mailing.senders`) | The From identities a list can use |
| `MailboxReader` | `NullMailboxReader` | Read access to the bounce mailbox |

Bind your own implementation through `mailing.sender_identity_provider` and `mailing.mailbox_reader`.

## Permissions and navigation

Permissions are pushed into the config key named by `mailing.permissions_config_key`: `mailing.lists`, `mailing.subscribers` and `mailing.settings` (the transport of a list). Menu items are pushed into `mailing.navigation_config_key`.

## Testing

```bash
composer update
vendor/bin/pest
```

## License

MIT
