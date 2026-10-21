# Installation

## Requirements

- PHP 8.5
- Laravel 13
- Inertia 3 (`inertiajs/inertia-laravel` and `@inertiajs/vue3`)
- `tranquil-tools/laravel-vue-form-builder` 1.10+ and `tranquil-tools/laravel-vue-table-builder` 1.9+
  for the admin. Composer installs them as dependencies; their front-end setup is described in their
  own documentation.
- Optional: `j87nl/laravel-cms`, which gets a [sign-up block](public/cms-block.md).
- Optional: `lettermint/lettermint-laravel` for the [Lettermint driver](delivery/lettermint.md).

## 1. Install via Composer

```bash
composer require j87nl/laravel-mailing
```

The service provider `J87NL\Mailing\MailingServiceProvider` is auto-discovered.

## 2. Publish the config and migrate

```bash
php artisan vendor:publish --tag=mailing-config
php artisan migrate
```

This creates `config/mailing.php`. See [Configuration](configuration.md) for every key.

The migrations are loaded from the package; there is nothing to publish for them. They create these
tables:

| Table                     | Holds                                                        |
| ------------------------- | ------------------------------------------------------------ |
| `mailing_lists`           | Lists, with their transport, sender and throttles            |
| `mailing_subscribers`     | One row per email address                                    |
| `mailing_subscriptions`   | One row per address and list, with status and unsubscribe token |
| `mailing_suppressions`    | Addresses that never receive mail                            |
| `mailing_consent_records` | The consent history of every subscriber                      |
| `mailing_imports`         | CSV imports and their results                                |
| `mailing_campaigns`       | Campaigns                                                    |
| `mailing_sends`           | One row per campaign and recipient                           |
| `mailing_bounce_events`   | Hard and soft bounces                                        |
| `mailing_webhook_events`  | Processed webhook and bounce report ids, for deduplication   |
| `mailing_rate_counters`   | Sending throttle counters                                    |

## 3. Configure a sender

Every list sends from a sender identity. The default identity reads the `.env`:

```env
MAILING_FROM_ADDRESS=news@example.org
MAILING_FROM_NAME="Example"
```

Without these it falls back to `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME`. A list cannot be created
while no valid sender is configured. See [Mailing lists](lists.md#sender-identity).

## 4. Point the layout at your site

The public pages extend the Blade layout in `mailing.layout`. The default
`mailing::layouts.default` is a bare HTML page. Use your own layout; it must yield `title`, `head`
and `content`:

```php
// config/mailing.php
'layout' => 'layouts.public',
```

## 5. Grant the permissions

The admin routes use Laravel's `can` middleware with five abilities. Define them with gates or a
permission system of your own. The package defines a sixth, `mailing.access`, for the menu item
itself. See [Permissions and navigation](permissions.md).

## 6. Set up the admin front-end

The admin pages are Vue files in `resources/js/pages` of this package. Add an alias, a page
resolver and a Tailwind source. See [Admin front-end](admin-frontend.md).

## 7. Run the scheduler and a queue worker

The package registers its own schedule: `mailing:dispatch` every minute, `mailing:process-bounces`
every five minutes and `mailing:prune` daily at 03:15. Make sure the Laravel scheduler runs:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

Campaign messages are jobs on the `mailing` queue (`mailing.sending.queue`). Confirmation mails are
queued mailables on your default queue. Run a worker that serves both:

```bash
php artisan queue:work --queue=mailing,default
```

Use a real queue connection, not `sync`. With `sync`, a sign-up of a new address takes noticeably
longer than one of a known address, because the confirmation mail is sent during the request. See
[Security](security.md#timing).

See [Artisan commands](artisan-commands.md) to turn the built-in schedule off.

## 8. Publish the views (optional)

```bash
php artisan vendor:publish --tag=mailing-views
```

This copies the Blade views to `resources/views/vendor/mailing`: the public pages, the sign-up form
partial, the CMS block and the mail templates.
