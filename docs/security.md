# Security

## Routes

### Public routes

| Method | URL                               | Name                         | Rate limit                 |
| ------ | --------------------------------- | ---------------------------- | -------------------------- |
| GET    | `/mailing/{list:key}/subscribe`   | `mailing.subscribe.show`     |                            |
| POST   | `/mailing/{list:key}/subscribe`   | `mailing.subscribe.store`    | 30 per minute, plus the sign-up limits |
| GET    | `/mailing/confirm/{subscription}` | `mailing.confirm.show`       | 30 per minute              |
| POST   | `/mailing/confirm/{subscription}` | `mailing.confirm.store`      | 30 per minute              |
| GET    | `/mailing/unsubscribe/{token}`    | `mailing.unsubscribe.show`   | 10 per minute per token    |
| POST   | `/mailing/unsubscribe/{token}`    | `mailing.unsubscribe.store`  | 10 per minute per token    |
| POST   | `/mailing/webhooks/{driver}`      | `mailing.webhooks`           | 600 per minute             |

They run without the `web` middleware group: no session, no cookies, no CSRF token. Each POST is
protected in its own way:

- **Sign-up:** a honeypot, a signed form token with a minimum and maximum age, and rate limits. See
  [Sign-up](public/signup.md).
- **Confirmation:** a temporary signed URL. The GET only shows a button.
- **Unsubscribe:** a random 48-character token per subscription. The GET only shows the choices.
- **Webhooks:** the signature check of the driver.

Adding `web` to `mailing.routes.public_middleware` would add a session cookie and CSRF
verification. The sign-up form has no CSRF field, and one-click unsubscribe POSTs from mail clients
and webhook calls carry none either, so all of them would be rejected. Leave it out.

### Admin routes

The admin routes use `mailing.routes.admin_middleware` (default `web` and `auth`) plus the ability
checks from [Permissions and navigation](permissions.md).

The tables on the admin pages are TranquilTools tables. Their `authorize()` methods check the same
abilities, which covers the table builder routes such as the subscriber CSV export. Run those routes
with `web` and `auth`, as the table builder documentation recommends:

```php
// config/table-builder.php
'routes' => [
    'middleware' => ['web', 'auth'],
],
```

## Enumeration

The sign-up response does not tell whether an address is new, pending, subscribed or suppressed. A
bot, an address over its limit and a real sign-up all get the same redirect to the success message.

### Timing

One difference remains. A new or pending address queues a confirmation mail; a subscribed or
suppressed address does not. That makes the response slightly slower for the first group. This is
an accepted trade-off. It only holds while the queue connection is not `sync`: with `sync`, the mail
is sent during the request and the difference becomes large.

## Mail volume

- A pending address gets at most one confirmation mail per `mailing.confirmation.resend_after_hours`.
- An address can be requested at most `per_address_per_hour` times an hour and
  `per_address_per_day` times a day.
- The site accepts at most `global_per_hour` sign-up requests an hour.
- Test sends are limited to five addresses at a time and ten mails per admin per hour, and never go
  to suppressed addresses.

## Content

- The campaign body is sanitized on save and again on render, through the `HtmlSanitizer` contract.
- The link check of the pre-flight only requests public IPv4 addresses, pins each request to the
  checked address, checks every redirect hop, and has a total time budget. Admins cannot use it to
  reach internal services.
- The `return_to` field of the sign-up form only accepts a local path, so the success redirect
  cannot point to another site.

## Signatures and app.key

The sign-up form token and the VERP Return-Path are signed with an HMAC keyed with `app.key`.
Rotating the key invalidates open sign-up forms and makes VERP addresses of earlier sends
unrecognizable. Confirmation links are Laravel signed URLs and depend on `app.key` as well.

## Logged actions

Two actions leave no consent record, because nothing remains to attach it to. They write an `info`
log entry instead, with the actor id:

- erasing a subscriber without keeping the address suppressed,
- lifting a suppression of an address that has no subscriber.
