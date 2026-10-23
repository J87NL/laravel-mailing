# Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=mailing-config
```

This creates `config/mailing.php`. Every key below shows its default.

## Integration with the host application

```php
'permissions_config_key' => 'j87nl.permissions',
'navigation_config_key' => 'j87nl.navigation',
'section_navigation' => true,
'host_sections' => [],
'toast_flash_key' => 'toast',
```

| Key                      | Description                                                                                       |
| ------------------------ | ------------------------------------------------------------------------------------------------- |
| `permissions_config_key` | The config key the package pushes its permission names into. See [Permissions and navigation](permissions.md). |
| `navigation_config_key`  | The config key the package pushes its `mailing` menu item into. See [Permissions and navigation](permissions.md#navigation). |
| `section_navigation`     | `false` hides the module submenu on every page. See [Sections in the host menu](permissions.md#sections-in-the-host-menu). |
| `host_sections`          | Keys of the sections your application shows in its own menu, for example `['lists', 'suppressions']`. They leave the submenu, and their pages show no submenu. See [Sections in the host menu](permissions.md#sections-in-the-host-menu). |
| `toast_flash_key`        | The Inertia flash key for success messages. The value is `['type' => 'success', 'message' => '...']`. |

## Timezone

```php
'timezone' => env('MAILING_TIMEZONE', 'Europe/Amsterdam'),
```

Campaign times are entered and shown in this timezone, and the daily cap of a list resets at
midnight in it. An empty value falls back to `app.timezone`.

## Layout

```php
'layout' => 'mailing::layouts.default',
```

The Blade layout the public pages extend. It must yield `title`, `head` and `content`.

## Routes

```php
'routes' => [
    'public_prefix' => 'mailing',
    'public_middleware' => [],
    'admin_prefix' => 'admin/mailing',
    'admin_middleware' => [
        'web',
        'auth',
    ],
],
```

| Key                 | Description                                                                                           |
| ------------------- | ----------------------------------------------------------------------------------------------------- |
| `public_prefix`     | URL prefix of the sign-up, confirmation, unsubscribe and webhook routes.                              |
| `public_middleware` | Extra middleware for the public routes. `SubstituteBindings` is always added. The default has no `web` group, so no session, cookies or CSRF. |
| `admin_prefix`      | URL prefix of the admin routes.                                                                       |
| `admin_middleware`  | Middleware for the admin routes. The permission checks come on top of these.                          |

See [Security](security.md#routes) before adding `web` to the public routes.

## Pages

```php
'pages' => [
    'form' => 'mailing/Form',
    'lists_index' => 'mailing/lists/Index',
    'subscribers_index' => 'mailing/subscribers/Index',
    'subscribers_show' => 'mailing/subscribers/Show',
    'suppressions_index' => 'mailing/suppressions/Index',
    'imports_index' => 'mailing/imports/Index',
    'campaigns_index' => 'mailing/campaigns/Index',
    'campaigns_show' => 'mailing/campaigns/Show',
],
```

The Inertia component names the admin controllers render. Change one to render a page of your own.
See [Admin front-end](admin-frontend.md).

## Senders

```php
'senders' => [
    'default' => [
        'email' => env('MAILING_FROM_ADDRESS', env('MAIL_FROM_ADDRESS')),
        'name' => env('MAILING_FROM_NAME', env('MAIL_FROM_NAME')),
    ],
],

'sender_identity_provider' => ConfigSenderIdentityProvider::class,
```

The From identities a list can use, keyed by an identifier. An entry without a valid email address
is ignored. `sender_identity_provider` is the class bound to the `SenderIdentityProvider` contract;
the default reads `senders`. See [Mailing lists](lists.md#sender-identity) and
[Contracts](contracts.md#senderidentityprovider).

## Contracts

```php
'mailbox_reader' => NullMailboxReader::class,
'html_sanitizer' => BasicHtmlSanitizer::class,
```

The classes bound to `MailboxReader` and `HtmlSanitizer`. The default mailbox reader reads nothing,
so bounce processing does nothing until you bind your own. See [Contracts](contracts.md). The
`PostfixLogReader` is set under [Postfix log](#postfix-log).

## Rich text

```php
'rich_text' => [
    'editor' => 'hugerte',
    'options_config_key' => 'cms.rich_text.editor_options',
],
```

| Key                  | Description                                                                                  |
| -------------------- | -------------------------------------------------------------------------------------------- |
| `editor`             | The form builder editor key of the campaign body field. The editor must be registered in your front-end, otherwise the form builder renders a textarea. |
| `options_config_key` | A config key whose array is passed to the editor as options. The default reuses the CMS editor options. |

## Transactional mailer

```php
'transactional_mailer' => env('MAILING_TRANSACTIONAL_MAILER'),
```

The mailer that sends confirmation mails. `null` uses the default mailer of the application.
Campaigns use the mailer of their list instead.

## List defaults

```php
'list_defaults' => [
    'mailer' => env('MAILING_MAILER', 'smtp'),
    'throttle_per_minute' => 20,
    'daily_cap' => 500,
],
```

The transport settings a new list starts with. They can be changed per list afterwards, see
[Mailing lists](lists.md#transport).

## List-Id domain

```php
'list_id_domain' => env('MAILING_LIST_ID_DOMAIN'),
```

The domain in the `List-Id` header and in the Message-ID of campaign mails. Empty uses the host of
`app.url`, or `localhost` when that has none.

## Drivers

```php
'mailer_drivers' => [
    'postfix' => 'postfix',
    'smtp' => 'postfix',
    'lettermint' => 'lettermint',
],

'drivers' => [
    'postfix' => [
        'class' => PostfixDriver::class,
    ],
    'lettermint' => [
        'class' => LettermintDriver::class,
        'webhook_secret' => env('LETTERMINT_WEBHOOK_SECRET'),
        'tolerance' => 300,
    ],
],
```

| Key                                 | Description                                                                         |
| ----------------------------------- | ----------------------------------------------------------------------------------- |
| `mailer_drivers`                    | Maps a mailer name from `config/mail.php` to a driver key. A mailer that is not listed uses `postfix`. |
| `drivers.<key>.class`               | The `MailingDriver` class of a driver.                                              |
| `drivers.lettermint.webhook_secret` | The secret that signs Lettermint webhooks.                                          |
| `drivers.lettermint.tolerance`      | The maximum age of a webhook signature in seconds.                                  |

See [Drivers](delivery/drivers.md).

## Sending

```php
'sending' => [
    'queue' => env('MAILING_QUEUE', 'mailing'),
    'retry_minutes' => [
        15,
        60,
        240,
    ],
    'stale_minutes' => 30,
    'scan_factor' => 5,
    'domain_limits' => [
        'gmail.com' => 5,
        'googlemail.com' => 5,
        'outlook.com' => 5,
        'hotmail.com' => 5,
        'live.nl' => 5,
        'ziggo.nl' => 5,
        'kpnmail.nl' => 5,
    ],
],
```

| Key             | Description                                                                                    |
| --------------- | ---------------------------------------------------------------------------------------------- |
| `queue`         | The queue of the `SendCampaignMessage` jobs.                                                   |
| `retry_minutes` | The delay before each retry after a temporary failure. Three entries means three retries.       |
| `stale_minutes` | A send that stays in `sending` longer than this is marked `failed`.                            |
| `scan_factor`   | Each dispatcher run looks at `throttle_per_minute × scan_factor` sends per campaign.           |
| `domain_limits` | The maximum number of sends per minute to a recipient domain, across all lists.                |

See [Sending](campaigns/sending.md).

## Bounces

```php
'bounces' => [
    'domain' => env('MAILING_BOUNCE_DOMAIN'),
    'local_part' => 'bounces',
    'soft_limit' => 3,
    'soft_window_days' => 30,
],
```

| Key                | Description                                                                              |
| ------------------ | ---------------------------------------------------------------------------------------- |
| `domain`           | The domain of the VERP Return-Path the Postfix driver sets. Empty sets no Return-Path.   |
| `local_part`       | The local part before the `+`: `bounces+<send id>-<signature>@<domain>`.                 |
| `soft_limit`       | Soft bounces for this many different messages suppress the address.                    |
| `soft_window_days` | The window in days in which those soft bounces are counted.                              |

See [Bounces](delivery/bounces.md).

## Postfix log

```php
'postfix_log' => [
    'path' => env('MAILING_POSTFIX_LOG_PATH'),
    'reader' => FilePostfixLogReader::class,
    'queue_ttl_hours' => 48,
],
```

| Key               | Description                                                                                       |
| ----------------- | ------------------------------------------------------------------------------------------------- |
| `path`            | The Postfix log file `mailing:read-postfix-log` reads. Empty (the default) turns the feature off and keeps the command out of the schedule. |
| `reader`          | The class bound to the `PostfixLogReader` contract. The default reads `path` incrementally and follows log rotation. |
| `queue_ttl_hours` | How long the queue id of one of your sends is remembered after its last `message-id` or `deferred` line. |

See [Postfix delivery log](delivery/postfix.md).

## Pre-flight

```php
'preflight' => [
    'dns_resolver' => NativeDnsResolver::class,
    'spam_scorer' => RspamdSpamScorer::class,
    'rspamd' => [
        'url' => env('MAILING_RSPAMD_URL'),
        'password' => env('MAILING_RSPAMD_PASSWORD'),
    ],
    'spam_threshold' => 5.0,
    'link_budget_seconds' => 20,
    'sending_ips' => array_values(array_filter(explode(',', (string) env('MAILING_SENDING_IPS', '')))),
    'blocklists' => [
        'zen.spamhaus.org',
        'b.barracudacentral.org',
        'bl.spamcop.net',
    ],
    'spf' => [
        'postfix' => array_values(array_filter(explode(',', (string) env('MAILING_SPF_POSTFIX', '')))),
        'lettermint' => [],
    ],
    'dkim_selectors' => [
        'postfix' => array_values(array_filter(explode(',', (string) env('MAILING_DKIM_SELECTORS_POSTFIX', '')))),
        'lettermint' => [],
    ],
],
```

| Key                      | Description                                                                                 |
| ------------------------ | ------------------------------------------------------------------------------------------- |
| `dns_resolver`           | The class bound to `DnsResolver`.                                                           |
| `spam_scorer`            | The class bound to `SpamScorer`.                                                            |
| `rspamd.url`             | The base URL of an Rspamd controller. Empty skips the spam score.                           |
| `rspamd.password`        | Sent as the `Password` header when set.                                                     |
| `spam_threshold`         | A score at or above this blocks scheduling.                                                 |
| `link_budget_seconds`    | The total time the link check may take.                                                     |
| `sending_ips`            | The IPv4 addresses checked against the blocklists, comma separated in `MAILING_SENDING_IPS`. |
| `blocklists`             | The DNS blocklist zones.                                                                    |
| `spf.<driver>`           | Text each SPF record must contain for that driver, for example `ip4:203.0.113.10`.          |
| `dkim_selectors.<driver>` | The DKIM selectors that must have a record for that driver.                                |

See [Pre-flight checks](campaigns/preflight.md).

## Sign-up

```php
'signup' => [
    'per_ip_per_hour' => 10,
    'per_address_per_hour' => 3,
    'per_address_per_day' => 6,
    'global_per_hour' => 60,
    'min_seconds' => 3,
    'max_seconds' => 172800,
    'success_query' => 'aangemeld',
    'privacy_url' => env('MAILING_PRIVACY_URL'),
],
```

| Key                    | Description                                                                         |
| ---------------------- | ----------------------------------------------------------------------------------- |
| `per_ip_per_hour`      | Sign-up attempts per IP address per hour.                                           |
| `per_address_per_hour` | Sign-up requests per email address per hour.                                        |
| `per_address_per_day`  | Sign-up requests per email address per 24 hours.                                    |
| `global_per_hour`      | Sign-up requests across the whole site per hour.                                    |
| `min_seconds`          | A form submitted faster than this after rendering is treated as a bot. A form shown again after a `422` may be posted at once. |
| `max_seconds`          | A form token older than this is expired (default 48 hours).                         |
| `success_query`        | The query parameter that marks a successful sign-up in the redirect URL.            |
| `privacy_url`          | The privacy statement the sign-up form links to: a local path starting with one `/` (query and fragment allowed) or an `http`/`https` URL with a host, trimmed. Anything else, or no value, shows no link. See [Sign-up](public/signup.md). |

See [Sign-up](public/signup.md).

A host that published `config/mailing.php` before 0.2.2 has its own `signup` array. Laravel merges package config only one level deep, so add `'privacy_url' => env('MAILING_PRIVACY_URL'),` to that array yourself, or `MAILING_PRIVACY_URL` has no effect.

## Confirmation

```php
'confirmation' => [
    'valid_days' => 7,
    'resend_after_hours' => 24,
],
```

| Key                  | Description                                                                 |
| -------------------- | --------------------------------------------------------------------------- |
| `valid_days`         | How long the signed confirmation link is valid.                             |
| `resend_after_hours` | A pending address gets at most one confirmation mail in this many hours.    |

## Import

```php
'import' => [
    'max_kilobytes' => 1024,
    'max_rows' => 5000,
    'confirmations_per_minute' => 20,
],
```

| Key                        | Description                                                         |
| -------------------------- | ------------------------------------------------------------------- |
| `max_kilobytes`            | The maximum upload size.                                            |
| `max_rows`                 | Rows after this are not processed and the import reports it.        |
| `confirmations_per_minute` | Confirmation mails of one import are delayed to stay under this rate. |

See [Imports](imports.md).

## Attachments

```php
'attachments' => [
    'disk' => env('MAILING_ATTACHMENTS_DISK', 'local'),
    'directory' => 'mailing/attachments',
    'extensions' => [
        'pdf',
        'jpg',
        'png',
    ],
    'max_total_kilobytes' => 7168,
    'warn_total_kilobytes' => 2048,
    'min_text_characters' => 200,
    'default_as_link' => false,
    'link_valid_days' => 30,
    'retention_days' => 30,
],
```

| Key                    | Description                                                                 |
| ---------------------- | --------------------------------------------------------------------------- |
| `disk`                 | A private filesystem disk for the files.                                    |
| `directory`            | The folder on that disk.                                                    |
| `extensions`           | Allowed types, checked on the file name and on the content.                |
| `max_total_kilobytes`  | The maximum size of all attachments of one campaign together.               |
| `warn_total_kilobytes` | The pre-flight check warns when the real attachments are larger.            |
| `min_text_characters`  | The pre-flight check warns when a mail with a real attachment has less text. |
| `default_as_link`      | Whether new files go as a download link by default.                         |
| `link_valid_days`      | How long a download link works after the mail is rendered.                 |
| `retention_days`       | Days after a campaign finished before its attachments are pruned; at least `link_valid_days`. |

See [Attachments](campaigns/attachments.md).

## Retention

```php
'retention' => [
    'unconfirmed_days' => 30,
    'unsubscribed_years' => 2,
    'send_records_months' => 13,
],
```

See [Retention](retention.md).

## Scheduling

```php
'scheduling' => [
    'enabled' => true,
],
```

`false` keeps the package from adding its commands to the Laravel schedule. See
[Artisan commands](artisan-commands.md#schedule).

## Environment variables

| Variable                         | Config key                               |
| -------------------------------- | ---------------------------------------- |
| `MAILING_TIMEZONE`               | `timezone`                               |
| `MAILING_FROM_ADDRESS`           | `senders.default.email`                  |
| `MAILING_FROM_NAME`              | `senders.default.name`                   |
| `MAILING_TRANSACTIONAL_MAILER`   | `transactional_mailer`                   |
| `MAILING_MAILER`                 | `list_defaults.mailer`                   |
| `MAILING_LIST_ID_DOMAIN`         | `list_id_domain`                         |
| `LETTERMINT_WEBHOOK_SECRET`      | `drivers.lettermint.webhook_secret`      |
| `MAILING_QUEUE`                  | `sending.queue`                          |
| `MAILING_ATTACHMENTS_DISK`       | `attachments.disk`                       |
| `MAILING_BOUNCE_DOMAIN`          | `bounces.domain`                         |
| `MAILING_POSTFIX_LOG_PATH`       | `postfix_log.path`                       |
| `MAILING_RSPAMD_URL`             | `preflight.rspamd.url`                   |
| `MAILING_RSPAMD_PASSWORD`        | `preflight.rspamd.password`              |
| `MAILING_SENDING_IPS`            | `preflight.sending_ips`                  |
| `MAILING_SPF_POSTFIX`            | `preflight.spf.postfix`                  |
| `MAILING_DKIM_SELECTORS_POSTFIX` | `preflight.dkim_selectors.postfix`       |
