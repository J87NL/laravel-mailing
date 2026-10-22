# Retention

`mailing:prune` runs daily at 03:15 and removes personal data the package no longer needs. The
periods come from `mailing.retention`:

```php
'retention' => [
    'unconfirmed_days' => 30,
    'unsubscribed_years' => 2,
    'send_records_months' => 13,
],
```

## Unconfirmed sign-ups

A `pending` subscription that has not changed for `unconfirmed_days` (30) days is handled in one of
two ways:

- **A first sign-up** is deleted with all consent records of that address for that list.
- **A renewed sign-up** of an address that unsubscribed before only loses the `requested` and
  `imported` records written after that unsubscribe. The subscription is set back to `unsubscribed`
  with the earlier unsubscribe date and confirmation date. The earlier history stays.

## Ended subscriptions

A subscription that is `unsubscribed`, `bounced` or `complained`, and ended more than
`unsubscribed_years` (2) years ago, is deleted with all consent records of that address for that
list.

## Send records

Send records, bounce events and processed webhook, bounce report and Postfix log ids older than
`send_records_months` (13) months are deleted.

Postfix queue ids of your sends are not kept that long: `mailing:read-postfix-log` deletes them when
Postfix logs `removed`, or `mailing.postfix_log.queue_ttl_hours` (48) after its last `deferred` line. See
[Postfix delivery log](delivery/postfix.md#privacy).

## Campaign attachments

The attachments of a campaign that is `sent` or `cancelled` are deleted, with their files, once the
campaign finished more than `mailing.attachments.retention_days` (30) days ago. That period is
never shorter than `mailing.attachments.link_valid_days`, so download links keep working until they
expire. Deleting a draft deletes its attachments at once. See
[Attachments](campaigns/attachments.md#retention).

## Subscribers

A subscriber without any subscription that has not changed for `unconfirmed_days` (30) days is
deleted, together with its remaining consent records.

## What stays

Suppressions are never pruned. An address that bounced, complained or unsubscribed from all lists
stays suppressed, even after its subscriber and history are gone.

## Erasure

To remove an address at once, use "Erase" on the subscriber page. See
[Subscribers](subscribers.md#erase).
