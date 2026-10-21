# Consent records

Every change to the consent of an address is stored in `mailing_consent_records`
(`J87NL\Mailing\Models\ConsentRecord`). The subscriber page shows them as its history.

## Fields

| Field           | Description                                                                    |
| --------------- | ------------------------------------------------------------------------------ |
| `subscriber_id` | The subscriber                                                                 |
| `list_id`       | The list, or `null` for actions on the address as a whole (suppressions)      |
| `action`        | A `ConsentAction`, see below                                                   |
| `source`        | Where it came from, see below                                                  |
| `ip`            | The IP address of the request, when there was one                              |
| `note`          | Free text, see below                                                           |
| `actor_id`      | The id of the admin user, for admin actions                                    |
| `created_at`    | The moment                                                                     |

## Actions

`J87NL\Mailing\Enums\ConsentAction`:

| Action         | Written when                                                    | Note holds                                |
| -------------- | --------------------------------------------------------------- | ----------------------------------------- |
| `requested`    | A sign-up starts or restarts a pending subscription             | The consent wording shown on the form     |
| `confirmed`    | The subscriber confirms                                         |                                           |
| `imported`     | A CSV import adds the address to a list                         | The consent note of the import, if any    |
| `unsubscribed` | A subscription ends, by the subscriber, an admin, a bounce, a complaint or a provider event |               |
| `suppressed`   | A new suppression is added for an address that has a subscriber | The suppression note                      |
| `unsuppressed` | An admin lifts a suppression                                    | The reason of the lifted suppression      |

The `confirmed` record repeats the source of the request, so the history shows where the consent
was given.

## Sources

A source is a key, optionally followed by `: ` and a detail of up to 200 characters. The keys are
constants on `J87NL\Mailing\Support\ConsentSource`:

| Constant           | Key                | Detail                                       |
| ------------------ | ------------------ | -------------------------------------------- |
| `SIGNUP`           | `signup`           | The path of the page with the form           |
| `IMPORT`           | `import`           | The consent source entered on the import     |
| `ADMIN`            | `admin`            |                                              |
| `ONE_CLICK`        | `one-click`        |                                              |
| `UNSUBSCRIBE_PAGE` | `unsubscribe-page` |                                              |
| `DSN`              | `dsn`              |                                              |
| `WEBHOOK`          | `webhook`          | The driver key, for example `lettermint`     |

`ConsentSource::make('signup', '/newsletter')` builds `signup: /newsletter`.
`ConsentSource::describe()` turns a stored source into the translated text from
`mailing::common.sources`, and returns unknown sources unchanged.

The same strings are stored on subscriptions (`consent_source`), suppressions (`source`) and bounce
events (`source`).

## Recording consent yourself

`J87NL\Mailing\Actions\RecordConsent` writes one record:

```php
use J87NL\Mailing\Actions\RecordConsent;
use J87NL\Mailing\Enums\ConsentAction;
use J87NL\Mailing\Support\ConsentSource;

app(RecordConsent::class)->handle(
    $subscriber,
    $list,
    ConsentAction::Unsubscribed,
    ConsentSource::ADMIN,
    ip: null,
    note: 'Asked by phone',
    actorId: $user->id,
);
```

It only writes the record. To change a subscription, use the actions that also record consent, such
as `Unsubscribe` or `SuppressAddress`.

## Retention

Consent records of ended subscriptions are deleted after `mailing.retention.unsubscribed_years`.
See [Retention](retention.md).
