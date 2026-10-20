# Subscribers

A subscriber is one email address (`mailing_subscribers`). A subscription links a subscriber to a
list (`mailing_subscriptions`) and carries the status. Subscribers are managed at
`/admin/mailing/subscribers` and need the `mailing.subscribers` permission.

Email addresses are stored trimmed and in lowercase, on subscribers and on suppressions.

## Subscription statuses

`J87NL\Mailing\Enums\SubscriptionStatus`:

| Status         | Meaning                                                        |
| -------------- | -------------------------------------------------------------- |
| `pending`      | Requested, waiting for the confirmation                        |
| `subscribed`   | Confirmed, or imported with documented consent                 |
| `unsubscribed` | Unsubscribed by the subscriber or an admin                     |
| `bounced`      | Ended by a hard bounce or repeated soft bounces                |
| `complained`   | Ended by a spam complaint                                      |

`pending` and `subscribed` count as active (`Subscription::isActive()`). Campaigns only go to
subscriptions that are `subscribed`, have a `confirmed_at` and whose address is not suppressed.

Every subscription gets a random 48-character `unsubscribe_token` when it is created. It is used in
the unsubscribe link and the `List-Unsubscribe` header.

## The subscriber list

The index shows every address with its name, a summary of its subscriptions per list and the date
it was added. It has a search on email and name, and a filter per list.

### CSV export

The index has an export button. It downloads `subscribers-YYYY-MM-DD.csv` with the columns of the
table, through the export route of the table builder. The export applies the current search and
filter.

## The subscriber page

The subscriber page shows:

- The address, the name, the creation date and, when the address is suppressed, the reason and date.
- The subscriptions per list, with status, consent source, confirmation and unsubscribe dates.
- The full [consent history](consent-records.md), newest first.

### Unsubscribe

An active subscription has an "Unsubscribe" action. It sets the status to `unsubscribed` and stores
a consent record with the source `admin` and the acting admin.

### Erase

"Erase" deletes the subscriber with everything that refers to it:

- the subscriptions and the consent records,
- the send records of its subscriptions,
- the bounce events of the address.

The erase form has a "keep suppressed" toggle, on by default. When it is on, the address is added
to the suppression list (reason `manual`, source `admin`) before the subscriber is deleted, so it
never receives mail again and cannot sign up again. An existing suppression stays either way.

When the toggle is off, the package writes an `info` log entry with the actor id, the subscriber
id and the list ids, because no record of the erasure remains in the database.

## Names

A sign-up can include a name. It is kept on the subscription as `requested_name` and only copied to
the subscriber on confirmation, and only when the subscriber has no name yet. An import sets the
name of a subscriber that has none.
