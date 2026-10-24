# Bounces

Bounces reach the package in three ways: as delivery status notifications in a mailbox (Postfix),
as webhook events (Lettermint), or from Postfix's own log (see
[Postfix delivery log](postfix.md)). All end up in the same rules.

## The rules

`J87NL\Mailing\Actions\ApplyBounce` records every bounce in `mailing_bounce_events` and then:

- **Hard bounce:** the send becomes `bounced` and the address is suppressed with the reason
  `hard_bounce`. Its subscriptions become `bounced`.
- **Soft bounce:** the send becomes `deferred`, without a retry. When the address has soft bounces
  for `mailing.bounces.soft_limit` (3) different sends within `mailing.bounces.soft_window_days`
  (30) days, the send becomes `bounced` and the address is suppressed with the reason
  `soft_bounces`.

Soft bounces without a known send each count as one message.

A send never moves back to an earlier status, see [Send statuses](../campaigns/sending.md#send-statuses).

## The bounce mailbox

`mailing:process-bounces` runs every five minutes. It reads the mailbox through the `MailboxReader`
contract:

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

`MailboxMessage` has a string `id` and the `raw` message. The default `NullMailboxReader` returns
nothing, so **bounce processing does nothing until you bind a reader** for your mailbox, for example
over IMAP or from a Maildir:

```php
// config/mailing.php
'mailbox_reader' => App\Mailing\MaildirBounceReader::class,
```

For every message the command:

1. Parses it as an RFC 3464 report (`multipart/report; report-type=delivery-status`). Other messages
   are ignored.
2. Finds the send: by the VERP address in `X-Original-To`, `Delivered-To`, `Envelope-To` or `To`,
   or else by the `Message-ID` of the original message in the report.
3. Applies the report once. The SHA-256 of the raw message is stored in `mailing_webhook_events`
   (driver `dsn`), so a report that was processed before is never applied again.
4. Archives the message through the reader, whether or not it was applied.

A reader that implements `BatchArchivingMailboxReader` archives in batches instead: the command
collects the messages and calls `archiveMany()` once per 100 messages and once for the rest, so an
IMAP reader can move them with one command. The messages are then archived after their reports were
applied; when a run stops in between, they are read again next time and not applied twice. See
[Contracts](../contracts.md#batcharchivingmailboxreader).

Per recipient in the report:

| `Action`  | `Status` | Result                                         |
| --------- | -------- | ---------------------------------------------- |
| `failed`  | `5.x.x`  | Hard bounce                                    |
| `failed`  | `4.x.x`  | Soft bounce                                    |
| `delayed` | any      | The send becomes `deferred`                    |
| other     | any      | Ignored                                        |

The bounce is applied to the address of the send. The diagnostic code is stored with it. The source
of the records is `dsn`.

## VERP

With `mailing.bounces.domain` set, the Postfix driver sets the Return-Path to
`<local_part>+<send id>-<signature>@<domain>`, for example
`bounces+1234-3f2a9c0d1e5b7a64@bounces.example.org`. Deliver mail for that address to the mailbox
your reader reads.

`J87NL\Mailing\Sending\Verp::address(int $sendId)` builds the address and
`Verp::sendId(string $address)` reads it back, returning `null` when the signature does not match.
The signature uses `app.key`; changing the key makes bounces of earlier sends unrecognizable by
VERP, but they can still be found by Message-ID.

## Webhook bounces

The Lettermint `message.hard_bounced` and `message.soft_bounced` events go through the same
`ApplyBounce` rules, with the source `webhook: lettermint`. See [Lettermint](lettermint.md).
