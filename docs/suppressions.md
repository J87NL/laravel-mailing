# Suppressions

The suppression list (`mailing_suppressions`) holds addresses that never receive mail from any
list. It is global: a suppression is not tied to a list. Suppressions are managed at
`/admin/mailing/suppressions` and need the `mailing.subscribers` permission.

## What a suppression blocks

- A sign-up for a suppressed address does nothing. The visitor sees the same success message.
- A confirmation link of a suppressed address does not confirm.
- An import skips suppressed addresses.
- A campaign does not include suppressed addresses, and a send whose address became suppressed
  after the campaign started is skipped.
- A test send skips suppressed addresses.

`Suppression::covers($email)` checks an address. It normalizes the address first.

## Reasons

`J87NL\Mailing\Enums\SuppressionReason`:

| Reason            | Added by                                                  | Subscriptions become |
| ----------------- | --------------------------------------------------------- | -------------------- |
| `hard_bounce`     | A hard bounce                                             | `bounced`            |
| `soft_bounces`    | Soft bounces for several messages, see [Bounces](delivery/bounces.md) | `bounced` |
| `complaint`       | A spam complaint from the provider                        | `complained`         |
| `manual`          | An admin, an erasure that keeps the address suppressed, or a provider `suppression.added` event | `unsubscribed` |
| `unsubscribe_all` | The subscriber chose "all lists" on the unsubscribe page  | `unsubscribed`       |

## Adding a suppression

`J87NL\Mailing\Actions\SuppressAddress` adds a suppression, ends every active subscription of the
address with the status from the table above, and writes the consent records:

```php
use J87NL\Mailing\Actions\SuppressAddress;
use J87NL\Mailing\Enums\SuppressionReason;
use J87NL\Mailing\Support\ConsentSource;

app(SuppressAddress::class)->handle(
    'someone@example.org',
    SuppressionReason::Manual,
    ConsentSource::ADMIN,
    note: 'Requested by email',
    actorId: $user->id,
);
```

The signature is
`handle(string $email, SuppressionReason $reason, string $source, ?string $note = null, ?int $actorId = null, ?string $ip = null): Suppression`.
When the address is already suppressed, the existing suppression is kept and returned.

The admin form "Add address" does the same with the reason `manual` and an optional note. An
address that is already on the list is rejected by validation.

## Lifting a suppression

Deleting a suppression needs the `mailing.settings` permission as well. It deletes the row and
writes an `unsuppressed` consent record. When no subscriber exists for the address, an `info` log
entry is written instead.

Lifting does not restore any subscription. The address can sign up again, or be imported again
when it has no subscription on that list.

A Lettermint `suppression.removed` event deletes a suppression only when it has the reason `manual`
and the same webhook source, so it only undoes what an earlier `suppression.added` event did.
