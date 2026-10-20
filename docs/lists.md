# Mailing lists

A list is the unit people subscribe to and campaigns are sent to. Lists are managed at
`/admin/mailing/lists` and need the `mailing.lists` permission.

## Fields

| Field         | Description                                                                                 |
| ------------- | ------------------------------------------------------------------------------------------- |
| `name`        | The display name, up to 120 characters. Shown on the public pages and in mails.             |
| `key`         | Lowercase letters, digits and dashes, up to 64 characters. Used in the sign-up URL and in the `List-Id` header. |
| `description` | Shown above the form on the sign-up page. Up to 1000 characters.                            |
| `reply_to`    | Optional Reply-To address of the list's mails.                                              |
| `is_public`   | Only public lists have a sign-up page and can be used in a sign-up block.                   |

The key is unique and cannot be `confirm`, `unsubscribe` or `webhooks`, because those are public
route segments. It cannot change after the list is created: it is part of URLs, of the `List-Id` of
every mail and of the sign-up blocks on your pages.

A list with subscriptions cannot be deleted.

When `j87nl/laravel-cms` is installed, the CMS `FlushPageCache` observer watches `MailingList`, so
list changes flush the CMS page cache and pages with a sign-up block show them.

## Transport

The transport of a list has its own form ("Sending" in the list table) and needs the
`mailing.settings` permission as well.

| Field                 | Description                                                                      |
| --------------------- | -------------------------------------------------------------------------------- |
| `mailer`              | A mailer from `config/mail.php`. `array`, `log`, `failover` and `roundrobin` are not offered. |
| `sender_identity`     | The From identity, see below.                                                    |
| `throttle_per_minute` | Campaign sends per minute, 1 to 600.                                            |
| `daily_cap`           | Campaign sends per day, 1 to 100000, or empty for no cap.                        |

A new list starts with `mailing.list_defaults` and the first configured sender identity.

The mailer decides which [driver](delivery/drivers.md) marks the messages, through
`mailing.mailer_drivers`.

Confirmation mails do not use the list mailer. They go through `mailing.transactional_mailer`, or
the default mailer when that is empty.

## Sender identity

The From address of a list is a sender identity: a key, an email address and an optional name. The
`SenderIdentityProvider` contract supplies them. The default `ConfigSenderIdentityProvider` reads
`mailing.senders`:

```php
'senders' => [
    'default' => [
        'email' => env('MAILING_FROM_ADDRESS', env('MAIL_FROM_ADDRESS')),
        'name' => env('MAILING_FROM_NAME', env('MAIL_FROM_NAME')),
    ],
    'market' => [
        'email' => 'market@example.org',
        'name' => 'The Market',
    ],
],
```

An entry without a valid email address is skipped. When the identity of a list no longer exists,
the list uses the first identity. When there is none at all, creating a list fails with an error
and sending throws a `RuntimeException`.

To load identities from somewhere else, see [Contracts](contracts.md#senderidentityprovider).

## Sign-up page

A public list has a sign-up page at `/mailing/{key}/subscribe` (route `mailing.subscribe.show`). The
list table links to it. See [Sign-up](public/signup.md).

## Models

`J87NL\Mailing\Models\MailingList` (table `mailing_lists`) has a `subscriptions()` relation and a
`subscribedCount()` method that counts subscriptions with the status `subscribed`.
