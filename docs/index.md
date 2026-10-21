# Laravel Mailing

A Laravel package for mailing lists: double opt-in sign-up, consent records, one-click unsubscribe
(RFC 8058), a global suppression list, CSV imports and campaigns sent through a slow, throttled queue.

The public pages (sign-up, confirmation, unsubscribe) are plain Blade. They set no cookies. Only the
confirmation page loads a small same-origin script, which submits its form. The admin is Inertia
and Vue, built on the TranquilTools form and table builders.

## How it works

1. Create a list in the admin. A public list gets a sign-up page at `/mailing/{key}/subscribe`.
2. A visitor signs up and receives a confirmation mail with a signed link. The page behind it posts
   its form with a small script, or with a button without JavaScript. Only that POST confirms the
   subscription.
3. Every step is stored as a consent record: the request, the confirmation, imports, unsubscribes
   and suppressions.
4. You compose a campaign, run the pre-flight checks, send a test and schedule it.
5. `mailing:dispatch` runs every minute. It snapshots the confirmed, unsuppressed subscribers and
   releases one queued job per address within the throttles of the list.
6. Bounces (from a mailbox or a webhook) update the send records and suppress addresses that keep
   failing. With your own Postfix, its mail log can report delivered, deferred and bounced as well.

## Quick example

Install the package and run the migrations:

```bash
composer require j87nl/laravel-mailing
php artisan vendor:publish --tag=mailing-config
php artisan migrate
```

Configure a sender in `.env`:

```env
MAILING_FROM_ADDRESS=news@example.org
MAILING_FROM_NAME="Example"
```

Grant the abilities the admin routes check:

```php
use App\Models\User;
use Illuminate\Support\Facades\Gate;

Gate::define('mailing.lists', fn (User $user): bool => $user->is_admin);
Gate::define('mailing.subscribers', fn (User $user): bool => $user->is_admin);
Gate::define('mailing.settings', fn (User $user): bool => $user->is_admin);
Gate::define('mailing.campaigns.edit', fn (User $user): bool => $user->is_admin);
Gate::define('mailing.campaigns.send', fn (User $user): bool => $user->is_admin);
```

Open `/admin/mailing/lists`, create a list with the key `news`, and the sign-up page is live at
`/mailing/news/subscribe`.

Your own mailables can carry the list headers by extending `ListMailable`:

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

## Next steps

- [Installation](installation.md): requirements and setup
- [Configuration](configuration.md): every config key
- [Sign-up](public/signup.md): the public form and its bot protection
- [Composing campaigns](campaigns/composing.md): write and send a mailing
- [Sending](campaigns/sending.md): throttles, retries and statuses
- [Drivers](delivery/drivers.md): Postfix, Lettermint and your own
- [Postfix delivery log](delivery/postfix.md): delivery results from Postfix's own log
