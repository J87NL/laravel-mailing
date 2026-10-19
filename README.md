# j87nl/laravel-mailing

Mailing lists for Laravel: double opt-in sign-up, consent records, RFC 8058 one-click unsubscribe, a global suppression list, CSV imports with a required consent source, and campaigns sent through a slow, throttled queue on the transport chosen per list. The public pages set no cookies and load no JavaScript; the admin is Inertia and Vue on TranquilTools forms and tables.

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

- **Sign-up:** a standalone page `/mailing/{list}/subscribe` and, when the CMS is installed, a `mailing_signup` block. Both are protected by a honeypot, a signed timestamp and rate limits per IP address, per address and overall, instead of CSRF and a session. The response is identical whether the address is new, pending, subscribed or suppressed. Only the response time can differ slightly, because a new or pending address queues a confirmation mail; that is an accepted trade-off. It only holds while the queue connection is not `sync`. A form token that is genuine but too old or too new gets a "please try again" with a fresh token instead of the silent success that bots get.
- **Confirmation mails are capped:** a pending address gets at most one confirmation mail per 24 hours (`mailing.confirmation.resend_after_hours`), and an address can be requested at most `mailing.signup.per_address_per_day` times a day.
- **Double opt-in:** a confirmation mail with a signed link valid for 7 days. The link opens a page with a button; only the POST confirms, so link scanners cannot confirm on someone's behalf.
- **Unsubscribe:** every subscription has its own token. A POST to the unsubscribe URL unsubscribes at once (RFC 8058 one-click); a GET only shows a page with "this list" and "all lists". "All lists" adds a suppression.
- **List headers:** mailables extending `ListMailable` carry `List-Unsubscribe` (HTTPS), `List-Unsubscribe-Post` and `List-Id`.
- **Consent records:** every request, confirmation, import, unsubscribe, suppression and lifted suppression is recorded with source, IP address, note and the acting admin. A sign-up request stores the consent wording that was shown.
- **Admin:** lists, the transport per list, subscribers with their history and a CSV export, manual unsubscribe, erasure of an address with its history (optionally keeping it suppressed), suppressions and imports. A list key cannot change after creation, and list changes flush the CMS page cache. Lifting a suppression needs `mailing.settings`.
- **Imports:** pending rows get a confirmation mail with their own wording, spread over time at `mailing.import.confirmations_per_minute`. A failing row is reported and the import continues.
- **Campaigns:** subject, preheader, a HugeRTE body run through the `HtmlSanitizer` contract and an editable plain-text part, rendered into one table-based layout with inlined styles. Pre-flight checks (SPF, DKIM, DMARC, alignment, blocklists for Postfix, spam score, links that resolve to public addresses only, text) block scheduling on red items; a test send goes to up to five addresses, at most ten per admin per hour, and never to suppressed addresses. Only drafts can be edited. Times are entered and shown in `mailing.timezone` (Europe/Amsterdam by default), and the daily cap resets at midnight there.
- **Sending:** `mailing:dispatch` runs every minute, starts due campaigns by snapshotting confirmed, unsuppressed subscribers, and releases sends within the per-list, per-day and per-domain limits (counters in the database). Each send is one job on the `mailing` queue that claims its row atomically, stores its Message-ID and hands the mail over. A 4xx answer is retried after 15 minutes, 1 hour and 4 hours; a row stuck in `sending` for 30 minutes becomes `failed` and is only retried by an admin. Campaigns can be paused, resumed and cancelled.
- **Drivers:** a `MailingDriver` per transport: `postfix` (VERP Return-Path, no webhooks) and `lettermint` (metadata, tag and idempotency key; signed webhooks on `/mailing/webhooks/lettermint`).
- **Bounces:** `mailing:process-bounces` reads the bounce mailbox through `MailboxReader` every five minutes and parses RFC 3464 reports. A hard bounce suppresses the address; three soft bounces within 30 days do the same. Webhook events feed the same rules.
- **Retention:** `mailing:prune` runs daily and deletes unconfirmed sign-ups after 30 days, the consent records of unsubscribed addresses after 2 years and send records after 13 months. A renewed sign-up that is never confirmed only loses its own request records; the earlier unsubscribe history stays and the subscription is ended again. Suppressions stay.

## Contracts

| Contract                 | Default                                            | Purpose                            |
| ------------------------ | -------------------------------------------------- | ---------------------------------- |
| `SenderIdentityProvider` | `ConfigSenderIdentityProvider` (`mailing.senders`) | The From identities a list can use |
| `MailboxReader`          | `NullMailboxReader`                                | Read access to the bounce mailbox  |
| `HtmlSanitizer`          | `BasicHtmlSanitizer`                               | Cleans the campaign body           |
| `DnsResolver`            | `NativeDnsResolver`                                | DNS lookups for the pre-flight     |
| `SpamScorer`             | `RspamdSpamScorer`                                 | Spam score for the pre-flight      |
| `MailingDriver`          | `PostfixDriver`, `LettermintDriver`                | Transport-specific marking         |

Bind your own implementations through the matching config keys.

## Permissions and navigation

Permissions are pushed into the config key named by `mailing.permissions_config_key`: `mailing.lists`, `mailing.subscribers`, `mailing.settings` (the transport of a list), `mailing.campaigns.edit` and `mailing.campaigns.send`. Menu items are pushed into `mailing.navigation_config_key`.

## Testing

```bash
composer update
vendor/bin/pest
```

## License

MIT
