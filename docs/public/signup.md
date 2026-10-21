# Sign-up

Every public list has a sign-up page. The same form can be placed on CMS pages with the
[sign-up block](cms-block.md). Both post to the same route.

| Method | URL                                | Name                       |
| ------ | ---------------------------------- | -------------------------- |
| GET    | `/mailing/{list:key}/subscribe`    | `mailing.subscribe.show`   |
| POST   | `/mailing/{list:key}/subscribe`    | `mailing.subscribe.store`  |

The POST route has Laravel's `throttle:30,1` middleware (30 requests per minute) on top of the
sign-up limits below. The prefix `mailing` is `mailing.routes.public_prefix`. A list that is not public answers `404`.

The form has an email address (required, up to 254 characters) and a name (optional, up to 120
characters). Below the fields it shows the consent wording from `mailing::signup.consent`. That
exact text is stored on the `requested` consent record.

When `mailing.signup.privacy_url` (`MAILING_PRIVACY_URL`) is set, the form links to that privacy
statement below the consent wording. The value is trimmed and then used when it is either:

- a local path that starts with one `/`, such as `/privacy`, `/privacy#cookies` or
  `/privacy?lang=en`, without whitespace; `//host` and `/\host` are refused, or
- an absolute URL with the scheme `http` or `https` (in any case) and a host.

Anything else leaves the link out. The link is not part of the stored consent wording.

## No session, no CSRF

The public routes run without the `web` middleware group. The form sets no cookie and needs no
session. Instead of a CSRF token it is protected by:

- **A honeypot.** A hidden field named `website`. A form with a value in it is a bot.
- **A signed timestamp.** A hidden field `form_token` with the render time and an HMAC over the
  list id and that time, keyed with `app.key`. A missing or wrong signature is a bot.
- **A minimum and maximum age.** A form posted within `mailing.signup.min_seconds` (3) of rendering
  is a bot. A form older than `mailing.signup.max_seconds` (48 hours) is expired.
- **Rate limits** per IP address, per email address and overall.

## What happens on a post

1. When the IP address is over `per_ip_per_hour`, the page answers `429` with a `Retry-After`
   header.
2. A bot gets the normal success redirect, so it learns nothing. It counts against its IP address.
3. An expired form is shown again with "This form has expired. Please try again." and a fresh
   token (`422`). It also counts against the IP address.
4. Invalid input is shown again with the validation errors (`422`).
5. The attempt counts against the IP address.
6. When the email address is over `per_address_per_hour` or `per_address_per_day`, the visitor gets
   the normal success redirect and nothing is sent.
7. When the site is over `global_per_hour`, the page answers `429` with a `Retry-After` header.
8. The subscription is requested, see below.
9. The visitor is redirected (`303`) to the success URL.

## Requesting the subscription

`J87NL\Mailing\Actions\RequestSubscription` handles a valid request:

- A suppressed address: nothing happens.
- An address already subscribed to the list: nothing happens.
- A pending address that got a confirmation mail less than `mailing.confirmation.resend_after_hours`
  (24) ago: nothing happens.
- Otherwise the subscription is created or reset to `pending`, a `requested` consent record is
  written and a [confirmation mail](confirmation.md) is queued.

If queueing the confirmation mail throws, the previous `confirmation_sent_at` is restored and the
exception is passed on.

The visitor sees the same success message in every case. See [Security](../security.md#timing) for
the one difference that remains.

## The success URL

After a sign-up the visitor goes back to the page the form was on:

```text
/newsletter?aangemeld=news#mailing-news
```

- The path is the `return_to` field of the form, when it is a local path (it starts with a single
  `/`, has no whitespace, query or fragment, and is at most 2048 characters). Otherwise it is the
  sign-up page of the list.
- The query parameter is `mailing.signup.success_query` (default `aangemeld`) with the list key as
  value.
- The fragment is the anchor of the form, `mailing-<key>`.

A form whose list key is in the success query shows "Almost done! We have sent you an email..."
instead of the fields.

## Rendering the form yourself

`J87NL\Mailing\Signup\SignupForm::data()` builds the view data of the form partial
`mailing::partials.signup-form`:

```php
use J87NL\Mailing\Signup\SignupForm;

$form = app(SignupForm::class)->data(
    $list,
    $request,
    returnTo: '/newsletter',
    title: 'Stay informed',
    intro: 'One mail a month about the market.',
    buttonLabel: 'Sign me up',
);
```

```blade
@include('mailing::partials.signup-form', $form)
```

The signature is:

```php
public function data(
    MailingList $list,
    Request $request,
    ?string $returnTo = null,
    ?MessageBag $errors = null,
    array $old = [],
    string $title = '',
    string $intro = '',
    string $buttonLabel = '',
): array
```

An empty button label uses `mailing::signup.button`. Validation errors of a failed post are shown on
the sign-up page itself, not on the page the form came from.

The static helpers `SignupForm::anchor($list)`, `SignupForm::succeeded($request, $list)`,
`SignupForm::successUrl($list, $returnTo)`, `SignupForm::successQuery()` and
`SignupForm::isLocalPath($path)` are public as well.

## Styling

The partial and the pages use plain class names, for example `mailing-signup`,
`mailing-signup__field`, `mailing-signup__trap` and `mailing-page`. Style them in your own CSS, and
hide `.mailing-signup__trap`, the honeypot field. Publish the views with `--tag=mailing-views` to
change the markup.
