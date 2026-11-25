# Host API

An application can put an address on a list and take it off again from its own code, for example
when it creates, changes or deletes a customer account. Both actions record consent like the rest
of the package and never send mail.

## Subscribing an address directly

`J87NL\Mailing\Actions\SubscribeAddress` puts an address on a list as `subscribed`, without a
confirmation mail:

```php
use J87NL\Mailing\Actions\SubscribeAddress;
use J87NL\Mailing\Models\MailingList;

$list = MailingList::query()
    ->where('key', 'customers')
    ->firstOrFail();

$subscription = app(SubscribeAddress::class)->handle(
    $list,
    $user->email,
    consentSource: 'Customer portal, new user',
    consentNote: 'Checkbox "Send me security and maintenance notices" ticked by the admin',
    actorId: auth()->id(),
    name: $user->name,
);
```

| Argument        | Description                                                                                   |
| --------------- | --------------------------------------------------------------------------------------------- |
| `list`          | The list.                                                                                     |
| `email`         | The address. It is trimmed and stored in lowercase.                                           |
| `consentSource` | Required. Where the consent comes from. Stored as `host: <source>`, up to 200 characters.     |
| `consentNote`   | Optional. How the consent was given, stored on the consent record.                           |
| `actorId`       | Optional. The id of the user who acted, stored on the consent record.                        |
| `name`          | Optional. Set on the subscriber when it has no name yet.                                      |

The result depends on what the address already has on that list:

| Existing subscription                | Result                                                                                   |
| ------------------------------------ | ---------------------------------------------------------------------------------------- |
| None                                 | A new `subscribed` subscription, with a `confirmed` consent record                       |
| `pending`                            | Confirmed: `subscribed`, with a `confirmed` consent record and the new source            |
| `subscribed`                         | Returned unchanged, nothing is recorded                                                  |
| `unsubscribed`, `bounced`, `complained` | `null`: the address left the list and is never subscribed again this way              |
| Any, while the address is suppressed | `null`: a suppressed address is never subscribed                                         |

Only the subscriber can come back after leaving a list, through the public sign-up and its
confirmation mail. An invalid address or an empty consent source throws an
`InvalidArgumentException`. Use `Suppression::covers($email)` to tell a suppressed address apart
from one that left the list.

## Unsubscribing an address from one list

`J87NL\Mailing\Actions\UnsubscribeAddress` ends the subscription of an address on one list:

```php
use J87NL\Mailing\Actions\UnsubscribeAddress;

app(UnsubscribeAddress::class)->handle(
    $list,
    $user->email,
    source: 'Customer deleted',
    actorId: auth()->id(),
);
```

It returns `true` when an active (`pending` or `subscribed`) subscription was ended, and `false`
when the address has no subscription on that list or it already ended. The subscription becomes
`unsubscribed` and gets an `unsubscribed` consent record with the source `host: <source>`. Other
lists and the suppression list are not touched. To stop all mail to an address, use
`SuppressAddress`, see [Suppressions](suppressions.md).

## Examples

| Event in the application                  | Call                                                                          |
| ----------------------------------------- | ----------------------------------------------------------------------------- |
| User created with the opt-in checkbox on  | `SubscribeAddress` with the new address                                       |
| Customer deleted                          | `UnsubscribeAddress` with the address                                         |
| Email address changed                     | `SubscribeAddress` with the new address, then `UnsubscribeAddress` with the old one |

When the address changes, the new address does not inherit the history of the old one: a new
address that is suppressed or left the list before stays off it.
