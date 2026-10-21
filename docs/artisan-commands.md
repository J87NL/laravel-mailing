# Artisan commands

The package has three commands. None of them takes options or arguments.

## `mailing:dispatch`

```bash
php artisan mailing:dispatch
```

Starts due campaigns and releases their sends within the throttles. It also marks stale sends as
failed and finishes campaigns that are done. It prints a summary:

```text
Started 1 campaigns, dispatched 20 sends, marked 0 interrupted sends as failed, finished 0 campaigns.
```

See [Sending](campaigns/sending.md#the-dispatcher).

## `mailing:process-bounces`

```bash
php artisan mailing:process-bounces
```

Reads the bounce mailbox through the `MailboxReader` contract and applies the delivery status
notifications. It prints how many reports were applied. See [Bounces](delivery/bounces.md).

## `mailing:prune`

```bash
php artisan mailing:prune
```

Deletes or ends unconfirmed sign-ups, deletes ended subscriptions with their consent records after
the retention period, and deletes old send records. It prints the counts. See
[Retention](retention.md).

## Schedule

The package adds the commands to the Laravel schedule itself:

| Command                   | Frequency          | Overlap lock |
| ------------------------- | ------------------ | ------------ |
| `mailing:dispatch`        | Every minute       | 5 minutes    |
| `mailing:process-bounces` | Every five minutes | 10 minutes   |
| `mailing:prune`           | Daily at 03:15     | 60 minutes   |

Each runs `withoutOverlapping()` with the lock time above. The scheduler of your application must
run every minute.

To schedule them yourself, turn this off:

```php
// config/mailing.php
'scheduling' => [
    'enabled' => false,
],
```
