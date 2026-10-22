# Security

## Routes

### Public routes

| Method | URL                               | Name                         | Rate limit                 |
| ------ | --------------------------------- | ---------------------------- | -------------------------- |
| GET    | `/mailing/{list:key}/subscribe`   | `mailing.subscribe.show`     |                            |
| POST   | `/mailing/{list:key}/subscribe`   | `mailing.subscribe.store`    | 30 per minute, plus the sign-up limits |
| GET    | `/mailing/confirm/{subscription}` | `mailing.confirm.show`       | 30 per minute              |
| POST   | `/mailing/confirm/{subscription}` | `mailing.confirm.store`      | 30 per minute              |
| GET    | `/mailing/assets/confirm.js`      | `mailing.confirm.script`     | 60 per minute              |
| GET    | `/mailing/unsubscribe/{token}`    | `mailing.unsubscribe.show`   | 10 per minute per token    |
| POST   | `/mailing/unsubscribe/{token}`    | `mailing.unsubscribe.store`  | 10 per minute per token    |
| GET    | `/mailing/attachments/{token}`    | `mailing.attachments.download` | 60 per minute per IP     |
| POST   | `/mailing/webhooks/{driver}`      | `mailing.webhooks`           | 600 per minute             |

They run without the `web` middleware group: no session, no cookies, no CSRF token. Each POST is
protected in its own way:

- **Sign-up:** a honeypot, a signed form token with a minimum and maximum age, and rate limits. See
  [Sign-up](public/signup.md).
- **Confirmation:** a temporary signed URL. Only the POST confirms. The GET page posts its form with
  a same-origin script, see [Content Security Policy](#content-security-policy).
- **Unsubscribe:** a random 48-character token per subscription. The GET only shows the choices.
- **Webhooks:** the signature check of the driver.
- **Attachment downloads:** a temporary signed URL with a random 40-character token, see
  [Attachments](campaigns/attachments.md#the-download-route).

Adding `web` to `mailing.routes.public_middleware` would add a session cookie and CSRF
verification. The sign-up form has no CSRF field, and one-click unsubscribe POSTs from mail clients
and webhook calls carry none either, so all of them would be rejected. Leave it out.

### Admin routes

The admin routes use `mailing.routes.admin_middleware` (default `web` and `auth`) plus the ability
checks from [Permissions and navigation](permissions.md). `GET /admin/mailing` (`mailing.home`)
needs `mailing.access` and redirects to the first section the user may open, or answers `403`.

The tables on the admin pages are TranquilTools tables. Their `authorize()` methods check the same
abilities, which covers the table builder routes such as the subscriber CSV export. Run those routes
with `web` and `auth`, as the table builder documentation recommends:

```php
// config/table-builder.php
'routes' => [
    'middleware' => ['web', 'auth'],
],
```

## Content Security Policy

The package sets no policy of its own. The public pages load no JavaScript, except the confirmation
page: its GET loads `/mailing/assets/confirm.js`, which submits the confirmation form. On that
route only, `AllowSameOriginScripts` sets `'self'` in the `script-src` of the host's policy, drops
`'none'` and keeps every other source and directive. Without a policy nothing is added. The POST
and every other public page keep the host's policy unchanged.

The middleware sees a policy set by the middleware in `mailing.routes.public_middleware`, not one
added by global middleware afterwards. Without JavaScript, or when the policy blocks the script,
the button on the page still confirms.

A mail scanner that runs JavaScript can confirm an address. That confirmation is recorded with the
scanner's IP address and does not prove consent. See
[Double opt-in](public/confirmation.md#mail-scanners).

## Enumeration

The sign-up response does not tell whether an address is new, pending, subscribed or suppressed. A
bot, an address over its limit and a real sign-up all get the same redirect to the success message.

### Timing

One difference remains. A new or pending address can queue a confirmation mail; a subscribed or
suppressed address does not, and neither does a pending address that already got one within
`resend_after_hours` or one over its per-address limit. That makes the response slightly slower for the first group. This is
an accepted trade-off. It only holds while the queue connection is not `sync`: with `sync`, the mail
is sent during the request and the difference becomes large.

## Mail volume

- A pending address gets at most one confirmation mail per `mailing.confirmation.resend_after_hours`.
- An address can be requested at most `per_address_per_hour` times an hour and
  `per_address_per_day` times a day.
- The site accepts at most `global_per_hour` sign-up requests an hour.
- Test sends are limited to five addresses at a time and ten mails per admin per hour, and never go
  to suppressed addresses. A failed attempt counts as well.

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
