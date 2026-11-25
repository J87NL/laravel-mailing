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
| `subscribed`   | Confirmed, imported with documented consent, or added by the application |
| `unsubscribed` | Unsubscribed by the subscriber or an admin                     |
| `bounced`      | Ended by a hard bounce or repeated soft bounces                |
| `complained`   | Ended by a spam complaint                                      |

`pending` and `subscribed` count as active (`Subscription::isActive()`). Campaigns only go to
subscriptions that are `subscribed`, have a `confirmed_at` and whose address is not suppressed.

Every subscription gets a random 48-character `unsubscribe_token` when it is created. It is used in
the unsubscribe link and the `List-Unsubscribe` header.

## The subscriber list

The index shows addresses with their name, a summary of their subscriptions per list and the date
they were added. It has a search on email and name, a filter per list and a status filter. The
status filter starts on "subscribed", so unsubscribed, pending, bounced and complained addresses
only show when you pick that status or "All statuses". With a list chosen, the status applies to the
subscription on that list.

### CSV export

The index has an export button. It downloads `subscribers-YYYY-MM-DD.csv` with the columns of the
table, through the export route of the table builder. The export applies the current search and
filter.

## Adding an address

The index has an "Add subscriber" button. It opens a form at `/admin/mailing/subscribers/create`
with the list, the address, an optional name, a consent source and a consent note (why this address
may receive mail). Both consent fields are required. The button is disabled, with the reason, while
no list exists.

The form calls [`SubscribeAddress`](host-api.md) with the acting admin, so the address is
`subscribed` at once without a confirmation mail, with a `confirmed` consent record that holds the
source `host: <consent source>`, the note and the admin. The admin then lands on the subscriber
page.

- An address that is already subscribed to that list stays as it is. Nothing is recorded and the
  message says it was already subscribed.
- A suppressed address is refused with a validation error on the address.
- An address whose subscription on that list ended (unsubscribed, bounced or complained) is refused
  as well. Only the subscriber can come back, through the public sign-up.

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

## From your application

An application can subscribe an address directly, without a confirmation mail, and unsubscribe it
from one list with `SubscribeAddress` and `UnsubscribeAddress`. See [Host API](host-api.md). The
[add form](#adding-an-address) in the admin uses the same action.

## Names

A sign-up can include a name. It is kept on the subscription as `requested_name` and only copied to
the subscriber on confirmation, and only when the subscriber has no name yet. An import sets the
name of a subscriber that has none.
