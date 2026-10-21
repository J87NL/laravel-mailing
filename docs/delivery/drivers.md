# Drivers

A list sends through a Laravel mailer. A driver adds what that transport needs on top: a
Return-Path, metadata or tags, and the handling of its webhooks. The driver never sends mail itself.

## Choosing the driver

`J87NL\Mailing\Drivers\DriverRegistry::for(MailingList $list)` picks the driver:

1. The mailer of the list is looked up in `mailing.mailer_drivers`.
2. The driver key found there is looked up in `mailing.drivers`, and its `class` is resolved from
   the container.
3. A mailer that is not mapped, or a driver that cannot be resolved, falls back to `PostfixDriver`.

```php
'mailer_drivers' => [
    'postfix' => 'postfix',
    'smtp' => 'postfix',
    'lettermint' => 'lettermint',
],
```

The driver also decides which checks the [pre-flight](../campaigns/preflight.md) runs: the blocklist
check runs for Postfix only, and the alignment check is a warning (`alignment.provider`) for every
other driver.

## Postfix

`J87NL\Mailing\Drivers\PostfixDriver`, key `postfix`. For your own mail server over SMTP.

- Sets a VERP Return-Path on every message when `mailing.bounces.domain` is set:
  `bounces+<send id>-<signature>@<bounce domain>`. The signature is an HMAC of the send id, keyed
  with `app.key`, so a bounce cannot be forged for another send. Test sends use send id `0`.
- Has no webhooks and does not report delivery. The campaign page shows the transport as Postfix.
- Bounces come back as delivery status notifications in a mailbox, see [Bounces](bounces.md).
- Delivered, deferred and bounced results can come from Postfix's own log, see
  [Postfix delivery log](postfix.md). The campaign page then says so.

## Lettermint

`J87NL\Mailing\Drivers\LettermintDriver`, key `lettermint`. See [Lettermint](lettermint.md).

## Writing your own driver

Implement `J87NL\Mailing\Contracts\MailingDriver`:

```php
<?php

declare(strict_types=1);

namespace App\Mailing;

use Illuminate\Http\Request;
use J87NL\Mailing\Contracts\MailingDriver;
use J87NL\Mailing\Models\Campaign;
use J87NL\Mailing\Models\Send;
use Symfony\Component\Mime\Email;

class AcmeDriver implements MailingDriver
{
    public function key(): string
    {
        return 'acme';
    }

    public function prepare(Email $message, Send $send): void
    {
        $message->getHeaders()->addTextHeader('X-Acme-Reference', (string) $send->id);
    }

    public function prepareTest(Email $message, Campaign $campaign): void {}

    public function hasWebhooks(): bool
    {
        return false;
    }

    public function verifyWebhook(Request $request): bool
    {
        return false;
    }

    public function parseWebhook(Request $request): array
    {
        return [];
    }

    public function reportsDelivery(): bool
    {
        return false;
    }
}
```

| Method              | Purpose                                                                            |
| ------------------- | ---------------------------------------------------------------------------------- |
| `key()`             | The driver key. The pre-flight reads `spf.<key>` and `dkim_selectors.<key>`, and runs the Postfix-only checks when it is `postfix`. |
| `prepare()`         | Marks a campaign message for one send, before it is handed to the mailer.          |
| `prepareTest()`     | Marks a test send and the message the spam score is measured on.                   |
| `hasWebhooks()`     | `true` opens `POST /mailing/webhooks/<key>` for this driver.                       |
| `verifyWebhook()`   | Checks the signature of a webhook request. `false` answers `401`.                  |
| `parseWebhook()`    | Turns a verified request into a list of `DeliveryEvent` objects.                   |
| `reportsDelivery()` | `true` shows the transport as a provider that reports delivery on the campaign page. |

Register it and map a mailer to it:

```php
// config/mailing.php
'mailer_drivers' => [
    'smtp' => 'postfix',
    'acme' => 'acme',
],

'drivers' => [
    // ...
    'acme' => [
        'class' => App\Mailing\AcmeDriver::class,
    ],
],
```

## Webhooks

| Method | URL                           | Name               |
| ------ | ----------------------------- | ------------------ |
| POST   | `/mailing/webhooks/{driver}`  | `mailing.webhooks` |

The route is limited to 600 requests per minute. It answers:

- `404` when the driver does not exist or has no webhooks,
- `401` with `{"message": "Invalid signature."}` when `verifyWebhook()` fails,
- `200` with `{"applied": <count>}` otherwise.

Each event is stored in `mailing_webhook_events` by driver and `eventId`. An event id that was seen
before is skipped, so a provider may deliver the same webhook more than once.

### Delivery events

`parseWebhook()` returns `J87NL\Mailing\Delivery\DeliveryEvent` objects:

```php
final readonly class DeliveryEvent
{
    public function __construct(
        public DeliveryEventType $type,
        public string $eventId,
        public ?int $sendId = null,
        public ?string $messageId = null,
        public ?string $recipient = null,
        public ?string $diagnostic = null,
    ) {}
}
```

The send is found by `sendId`, or else by `messageId`. The address is `recipient`, or else the
address of the send. `J87NL\Mailing\Actions\ApplyDeliveryEvent` applies them:

| `DeliveryEventType`  | Effect                                                                    |
| -------------------- | ------------------------------------------------------------------------- |
| `Delivered`          | Send becomes `delivered`                                                  |
| `Deferred`           | Send becomes `deferred`, without a retry time                             |
| `Failed`             | Send becomes `failed`                                                     |
| `HardBounce`         | A hard bounce, see [Bounces](bounces.md)                                  |
| `SoftBounce`         | A soft bounce, see [Bounces](bounces.md)                                  |
| `Complained`         | Send becomes `complained`; the address is suppressed (reason `complaint`) |
| `Unsubscribed`       | The subscription of the send is unsubscribed                              |
| `SuppressionAdded`   | The address is suppressed (reason `manual`)                               |
| `SuppressionRemoved` | A `manual` suppression added by the same webhook source is deleted        |

The consent and bounce records get the source `webhook: <driver key>`.
