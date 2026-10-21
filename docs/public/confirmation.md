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

| Method | URL                                    | Name                     |
| ------ | -------------------------------------- | ------------------------ |
| GET    | `/mailing/confirm/{subscription}`      | `mailing.confirm.show`   |
| POST   | `/mailing/confirm/{subscription}`      | `mailing.confirm.store`  |
| GET    | `/mailing/assets/confirm.js`           | `mailing.confirm.script` |

The confirmation routes are limited to 30 requests per minute, the script to 60.

A GET never confirms anything. For a pending subscription it shows a page with a form that posts
to the same signed URL, and loads a small same-origin script that submits that form. One click in
the mail is enough:

- The script is served by `mailing.confirm.script` and loaded by its relative URL, so a proxy that
  ends TLS never turns it into mixed content.
- It only submits the form marked with `data-mailing-auto-submit`. It waits until the page is
  visible and disables the button before it posts.
- Without JavaScript the "Yes, sign me up" button stays, and the visitor confirms with it.
- Expired and already confirmed pages do not load the script.

| Situation                                    | GET                       | POST                      |
| -------------------------------------------- | ------------------------- | ------------------------- |
| Unknown subscription or invalid signature    | "Link expired", `403`     | "Link expired", `403`     |
| Already subscribed                           | "Sign-up confirmed"       | "Sign-up confirmed"       |
| Pending, address not suppressed              | The form and the script   | Confirms, "Sign-up confirmed" |
| Pending, address suppressed                  | The form and the script   | "Link expired", `410`     |
| Any other status                             | "Link expired", `410`     | "Link expired", `410`     |

The GET only looks at the status. The suppression list is checked when the POST confirms.

An expired page links to the sign-up page when the list is public.

### Content Security Policy

The GET route runs `AllowSameOriginScripts` around the public middleware. When the response carries
a `Content-Security-Policy`, it sets `'self'` in `script-src`, drops `'none'` and keeps every other
source and directive. A policy without `script-src` gets `script-src 'self'`. Without a policy
nothing is added. The POST and every other public page keep the policy as it is.

The middleware only sees a policy that is set by middleware inside it: the route middleware in
`mailing.routes.public_middleware`. A policy added by global middleware is set after it, so a
`script-src 'none'` there blocks the script and the visitor needs the button.

### Mail scanners

Link scanners that only fetch the URL cannot confirm, because they do not run the script. A scanner
that does run JavaScript, such as a sandbox that renders the page in a full browser, does post the
form and confirms the address. This is an accepted trade-off, so people who forget a second click
are not lost.

Such a confirmation is recorded like any other, with the IP address of the scanner. It does not
prove that the owner of the inbox agreed.

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
