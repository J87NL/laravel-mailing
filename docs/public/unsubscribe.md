# Unsubscribe and list headers

Every subscription has its own unsubscribe token. Every campaign mail links to it in the footer and
in the `List-Unsubscribe` header.

## Routes

| Method | URL                               | Name                        |
| ------ | --------------------------------- | --------------------------- |
| GET    | `/mailing/unsubscribe/{token}`    | `mailing.unsubscribe.show`  |
| POST   | `/mailing/unsubscribe/{token}`    | `mailing.unsubscribe.store` |

Both are limited to 10 requests per minute per token (rate limiter `mailing-unsubscribe`). An
unknown token answers `404`.

## One-click unsubscribe (RFC 8058)

A POST with the body `List-Unsubscribe=One-Click`, as mail clients send it, unsubscribes from the
list at once. It never suppresses the address, even when the request asks for all lists.

## The unsubscribe page

A GET never changes anything. For an active subscription it shows two buttons:

- **"Unsubscribe from" and the list name**, for example "Unsubscribe from News". The subscription becomes `unsubscribed`.
- **"Unsubscribe from all lists".** The address is added to the suppression list with the reason
  `unsubscribe_all`, which ends every subscription of the address.

For a subscription that has already ended it shows the "You have been unsubscribed" page.

The consent records get the source `one-click` or `unsubscribe-page` and the IP address.

## Unsubscribing from code

```php
use J87NL\Mailing\Actions\Unsubscribe;
use J87NL\Mailing\Support\ConsentSource;

app(Unsubscribe::class)->handle($subscription, ConsentSource::ADMIN, actorId: $user->id);
```

The signature is
`handle(Subscription $subscription, string $source, ?string $ip = null, ?int $actorId = null, SubscriptionStatus $status = SubscriptionStatus::Unsubscribed): bool`.
It returns `false` when the subscription is not active.

## List headers

`J87NL\Mailing\Mail\ListHeaders::for(Subscription $subscription)` returns:

| Header                  | Value                                                    |
| ----------------------- | -------------------------------------------------------- |
| `List-Id`               | `"<list name>" <<list key>.<domain>>`                    |
| `List-Unsubscribe`      | `<https://example.org/mailing/unsubscribe/<token>>`      |
| `List-Unsubscribe-Post` | `List-Unsubscribe=One-Click`                             |

The domain is `mailing.list_id_domain`, or the host of `app.url`. The unsubscribe URL is an absolute
URL built from `app.url`, so `app.url` must be the public HTTPS address of the site.

Campaign mails always carry these headers. Test sends and previews use the placeholder token
`preview`, which does not belong to any subscription.

### Your own mailables

Extend `J87NL\Mailing\Mail\ListMailable` to give your own mails the same headers. Its constructor
takes the subscription, and `unsubscribeUrl()` returns the link for the body:

```php
<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use J87NL\Mailing\Mail\ListMailable;

class MarketReminder extends ListMailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'The market opens on Saturday');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.market-reminder',
            with: [
                'unsubscribeUrl' => $this->unsubscribeUrl(),
            ],
        );
    }
}
```

```php
Mail::to($subscription->subscriber->email)->send(new MarketReminder($subscription));
```

`ListMailable` overrides `headers()`. Sending such a mail does not check the subscription status or
the suppression list; do that before you send it.
