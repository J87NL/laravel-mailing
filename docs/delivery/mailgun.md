# Mailgun

The `mailgun` driver (`J87NL\Mailing\Drivers\MailgunDriver`) marks messages for Mailgun and handles
its webhooks. The transport itself is Laravel's built-in `mailgun` mailer.

## Setup

Install the Symfony Mailgun transport and its HTTP client:

```bash
composer require symfony/mailgun-mailer symfony/http-client
```

Add a mailer to `config/mail.php` and the credentials to `config/services.php`:

```php
// config/mail.php
'mailers' => [
    'mailgun' => [
        'transport' => 'mailgun',
    ],
],

// config/services.php
'mailgun' => [
    'domain' => env('MAILGUN_DOMAIN'),
    'secret' => env('MAILGUN_SECRET'),
    'endpoint' => env('MAILGUN_ENDPOINT', 'api.eu.mailgun.net'),
    'scheme' => 'https',
],
```

```env
MAILGUN_DOMAIN=mg.example.org
MAILGUN_SECRET=
MAILGUN_ENDPOINT=api.eu.mailgun.net
MAILING_MAILGUN_WEBHOOK_SIGNING_KEY=
```

Use `api.mailgun.net` as endpoint for a domain in the US region. The webhook signing key is on the
"Webhooks" page of the Mailgun dashboard ("HTTP webhook signing key").

`mailing.mailer_drivers` maps the mailer name `mailgun` to the driver by default. Choose the
`mailgun` mailer in the transport settings of a list. In Mailgun, add a webhook for the events
`delivered`, `permanent_fail`, `temporary_fail`, `complained` and `unsubscribed` that points at:

```text
https://example.org/mailing/webhooks/mailgun
```

## What the driver adds

On every campaign message:

| Marking                         | Value                    |
| ------------------------------- | ------------------------ |
| User variable `mailing_send_id` | The id of the send       |
| Tag                             | `campaign-<campaign id>` |

A test send gets the tag `campaign-<campaign id>-test` and no user variable.

The driver adds Symfony's `MetadataHeader` and `TagHeader`, and the Symfony Mailgun transports turn
them into what Mailgun reads:

| Transport                                        | User variable                         | Tag                         |
| ------------------------------------------------ | ------------------------------------- | --------------------------- |
| `mailgun+https` (Laravel's default, MIME upload) | `X-Mailgun-Variables` header          | `X-Mailgun-Tag` header      |
| `mailgun+api` (`'scheme' => 'api'`)              | `v:mailing_send_id` field             | `o:tag` field               |
| `mailgun+smtp`                                   | `X-Mailgun-Variables` header          | `X-Mailgun-Tag` header      |

The user variable has the prefix `mailing_` so it does not collide with variables that other
software on the same Mailgun domain sets.

The driver reports delivery, so the campaign page shows the transport as a provider. The
pre-flight shows the alignment check as orange (the provider handles it), skips the blocklist check,
and has no SPF entries or DKIM selectors to check by default. Set
`mailing.preflight.dkim_selectors.mailgun` to the selectors of your Mailgun domain to check them.

## Errors from the API

The Symfony Mailgun transport throws an `HttpTransportException` without a code. The package reads
the HTTP status from the response of that exception, so Mailgun errors are recognised like those of
any other mail API: a 401 or 403 as refused credentials, 408 as a timeout, 429 as a request to try
again later, another 4xx as rejected and a 5xx as a service that is temporarily unavailable.

The sending job retries 408, 429 and every 5xx and fails the send at once on every other 4xx. When
Mailgun could not be reached at all, the exception has no status and the send fails as interrupted,
because the mail may have been accepted. See [Sending](../campaigns/sending.md).

Mailgun has no idempotency key, so a retry after a 5xx that Mailgun had already accepted can
deliver the message twice.

## Webhook signature

`verifyWebhook()` follows the Mailgun documentation for webhook security:

- The JSON body holds a `signature` object with `timestamp`, `token` and `signature`.
- The signature is an HMAC-SHA256 of `<timestamp><token>` with
  `mailing.drivers.mailgun.webhook_signing_key`, compared with `hash_equals()`.
- The timestamp may be at most `mailing.drivers.mailgun.tolerance` (300) seconds away from now.

Without a signing key every webhook is rejected. Only the JSON webhooks are supported, not the
legacy form-encoded ones.

## Events

| Mailgun event                                        | Delivery event |
| ---------------------------------------------------- | -------------- |
| `delivered`                                          | `Delivered`    |
| `failed` with `severity` `temporary`                 | `SoftBounce`   |
| `failed` with `severity` `permanent` and a 5xx code  | `HardBounce`   |
| `failed` with `severity` `permanent` and another code | `Failed`      |
| `complained`                                         | `Complained`   |
| `unsubscribed`                                       | `Unsubscribed` |

A permanent failure only suppresses the address when the receiving server answered with a 5xx, the
same rule as for Postfix. Mailgun drops messages to addresses on its own suppression lists with a
6xx code (for example `605` for a previously bounced address); those fail the send without adding a
suppression here. Temporary failures are soft bounces: Mailgun reports one per attempt, and they
only suppress the address after soft bounces for three different messages, see
[Bounces](bounces.md).

Other events, such as `accepted`, `opened` and `clicked`, are ignored. So is every event without the
`mailing_send_id` user variable: the same Mailgun domain can carry mail of other software, and its
events answer `200` with `{"applied": 0}` instead of an error.

The payload fields the driver reads are `event-data.id`, `event-data.event`,
`event-data.severity`, `event-data.reason`, `event-data.recipient`,
`event-data.user-variables.mailing_send_id`, `event-data.message.headers.message-id` and
`event-data.delivery-status.code`, `.message` and `.description`. The code, message, description
and, for failures, the reason are stored as the response of the send. See
[Drivers](drivers.md#delivery-events) for what each event does.

## Unverified payloads

The payloads in
[`tests/fixtures/mailgun/`](https://github.com/J87NL/laravel-mailing/tree/main/tests/fixtures/mailgun)
in the package repository are written by hand from the Mailgun webhook documentation, not
recorded. The field names `event-data.user-variables`, `event-data.severity` and
`event-data.delivery-status.*` have not been checked against real deliveries. Record a real payload
for every event and compare it with the driver before a list moves to Mailgun.
