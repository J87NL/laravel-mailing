# Artisan commands

The package has four commands. Only `mailing:read-postfix-log` takes options or arguments.

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

## `mailing:read-postfix-log`

```bash
php artisan mailing:read-postfix-log
php artisan mailing:read-postfix-log --file=/var/log/mail.log.1
journalctl -t postfix/smtp -t postfix/cleanup -t postfix/qmgr -o short-iso | php artisan mailing:read-postfix-log -
```

Reads the new lines of the Postfix log at `mailing.postfix_log.path` and applies delivered, deferred
and bounced results to the sends they belong to. `--file` reads another file once from the start,
`-` reads standard input. Without a path, `--file` or `-` it does nothing. It prints how many results
were applied. See [Postfix delivery log](delivery/postfix.md).

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
| `mailing:read-postfix-log` | Every minute, only with `mailing.postfix_log.path` set | 10 minutes |

Each runs `withoutOverlapping()` with the lock time above. The scheduler of your application must
run every minute.

To schedule them yourself, turn this off:

```php
// config/mailing.php
'scheduling' => [
    'enabled' => false,
],
```
