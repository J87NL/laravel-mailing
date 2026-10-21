# Double opt-in

A sign-up only becomes a subscription after the address confirms it.

## The confirmation mail

`J87NL\Mailing\Actions\SendConfirmation` queues a `ConfirmSubscriptionMail` with a signed link:

```php
use J87NL\Mailing\Actions\SendConfirmation;

app(SendConfirmation::class)->handle($subscription);
```

The signature is `handle(Subscription $subscription, bool $imported = false, int $delayMinutes = 0): void`.

- The link is a temporary signed route to `mailing.confirm.show`, valid for
  `mailing.confirmation.valid_days` (7) days.
- The mail is queued through `mailing.transactional_mailer`, or the default mailer when that is
  empty, on the default queue.
- The From address and Reply-To are those of the list.
- `$imported = true` uses the import wording: "Would you like to receive the mailings of ...?"
  instead of "Confirm your sign-up for ...".
- `$delayMinutes` delays the queued mail. Imports use it to spread their mails.

It sets `confirmation_sent_at` on the subscription.

## The confirmation page

| Method | URL                                    | Name                    |
| ------ | -------------------------------------- | ----------------------- |
| GET    | `/mailing/confirm/{subscription}`      | `mailing.confirm.show`  |
| POST   | `/mailing/confirm/{subscription}`      | `mailing.confirm.store` |

Both are limited to 30 requests per minute.

Opening the link does not confirm anything. The GET shows a page with a "Yes, sign me up" button
that posts to the same signed URL. Link scanners and mail previews that follow the link cannot
confirm on someone's behalf.

| Situation                                    | GET                       | POST                      |
| -------------------------------------------- | ------------------------- | ------------------------- |
| Unknown subscription or invalid signature    | "Link expired", `403`     | "Link expired", `403`     |
| Already subscribed                           | "Sign-up confirmed"       | "Sign-up confirmed"       |
| Pending, address not suppressed              | The confirm button        | Confirms, "Sign-up confirmed" |
| Pending, address suppressed                  | The confirm button        | "Link expired", `410`     |
| Any other status                             | "Link expired", `410`     | "Link expired", `410`     |

The GET only looks at the status. The suppression list is checked when the POST confirms.

An expired page links to the sign-up page when the list is public.

## Confirming

`J87NL\Mailing\Actions\ConfirmSubscription::handle(Subscription $subscription, ?string $ip): bool`
confirms a pending subscription of an address that is not suppressed:

- The status becomes `subscribed`, and `consent_at`, `consent_ip` and `confirmed_at` are set.
- A name given at sign-up is copied to the subscriber when the subscriber has no name yet.
- A `confirmed` consent record is written with the source of the request and the IP address.

It returns `false` when the subscription is not pending or the address is suppressed.

## Resending

A new sign-up for a pending address sends a new confirmation mail once
`mailing.confirmation.resend_after_hours` (24) have passed since the last one. Unconfirmed
subscriptions are removed after `mailing.retention.unconfirmed_days`, see [Retention](../retention.md).
