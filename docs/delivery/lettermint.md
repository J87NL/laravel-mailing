# Lettermint

The `lettermint` driver (`J87NL\Mailing\Drivers\LettermintDriver`) marks messages for Lettermint and
handles its webhooks. The transport itself comes from the official package.

## Setup

Install the transport:

```bash
composer require lettermint/lettermint-laravel
```

Add a mailer to `config/mail.php`:

```php
'mailers' => [
    'lettermint' => [
        'transport' => 'lettermint',
        'route_id' => env('LETTERMINT_ROUTE_ID'),
    ],
],
```

Set the tokens and the webhook secret:

```env
LETTERMINT_PROJECT_TOKEN=
LETTERMINT_ROUTE_ID=
LETTERMINT_WEBHOOK_SECRET=
```

`mailing.mailer_drivers` maps the mailer name `lettermint` to the driver by default. Choose the
`lettermint` mailer in the transport settings of a list. Point the Lettermint webhook at:

```text
https://example.org/mailing/webhooks/lettermint
```

## What the driver adds

On every campaign message:

| Marking                      | Value                     |
| ---------------------------- | ------------------------- |
| Metadata `send_id`           | The id of the send        |
| Tag                          | `campaign-<campaign id>`  |
| `Idempotency-Key`            | `mailing-send-<send id>`  |

A test send gets the tag `campaign-<campaign id>-test`.

The driver reports delivery, so the campaign page shows the transport as a provider. The
pre-flight shows the alignment check as orange (the provider handles it), skips the blocklist check,
and has no SPF entries or DKIM selectors to check by default.

## Errors from the API

Lettermint answers over HTTP, so its errors carry an HTTP status instead of an SMTP reply code. The
status is recognised when the transport throws an `HttpTransportException` or wraps the exception of
the API client.

A failed test send shows the cause on the campaign page: a 401 or 403 as refused credentials, 408
as a timeout, 429 as a request to try again later, another 4xx as rejected and a 5xx as a service
that is temporarily unavailable. See
[Composing campaigns](../campaigns/composing.md#when-a-test-mail-fails).

**Known limitation:** the sending job does not make this distinction yet. For every transport it
retries a 4xx code as temporary and fails a 5xx code for good, see
[Sending](../campaigns/sending.md). For an HTTP transport that means a 401 or 403 is retried until
the retries run out, and a temporary 5xx is not retried. This is a follow-up, not fixed in this
version.

## Webhook signature

`verifyWebhook()` follows `Lettermint\Webhook::verify()`:

- The `X-Lettermint-Signature` header holds `t=<timestamp>` and one or more `v1=<signature>` parts,
  separated by commas.
- The `X-Lettermint-Delivery` header must equal the timestamp.
- The timestamp may be at most `mailing.drivers.lettermint.tolerance` (300) seconds away from now.
- The signature is an HMAC-SHA256 of `<timestamp>.<raw body>` with
  `mailing.drivers.lettermint.webhook_secret`.

Without a secret every webhook is rejected. One secret is used for all routes.

## Events

| Lettermint event         | Delivery event      |
| ------------------------ | ------------------- |
| `message.delivered`      | `Delivered`         |
| `message.soft_bounced`   | `SoftBounce`        |
| `message.hard_bounced`   | `HardBounce`        |
| `message.spam_complaint` | `Complained`        |
| `message.failed`         | `Failed`            |
| `message.suppressed`     | `Failed`            |
| `message.policy_rejected` | `Failed`           |
| `message.unsubscribed`   | `Unsubscribed`      |
| `suppression.added`      | `SuppressionAdded`  |
| `suppression.removed`    | `SuppressionRemoved` |

Other events are ignored. The payload fields the driver reads are `id`, `event`,
`data.metadata.send_id`, `data.message_id`, `data.recipient` (or `data.value`), `data.reason` and
`data.response.status_code`, `data.response.enhanced_status_code` and `data.response.content`. See
[Drivers](drivers.md#delivery-events) for what each event does.

## Unverified payloads

The payloads in
[`tests/fixtures/lettermint/`](https://github.com/J87NL/laravel-mailing/tree/main/tests/fixtures/lettermint)
in the package repository are written by hand from the Lettermint documentation,
not recorded. The field names `data.recipient`, `data.metadata.send_id` and `data.response.*` have
not been checked against real deliveries. Record a real payload for every event and compare it with
the driver before a list moves to Lettermint.
