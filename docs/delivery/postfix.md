# Postfix delivery log

Without help, a send through your own Postfix ends at `handed_over`: Postfix accepted the mail, and
only a bounce message tells you more later. Postfix itself logs what happened next for every
recipient. `mailing:read-postfix-log` reads that log and moves the send on:

| Postfix logs                                  | Delivery event | Send becomes | Response on the campaign page        |
| --------------------------------------------- | -------------- | ------------ | ------------------------------------ |
| `status=sent`                                 | `Delivered`    | `delivered`  | The reply of the receiving server, e.g. `250 2.0.0 Ok: queued as 4F1A2B3C4D` |
| `status=deferred`                             | `Deferred`     | `deferred`   | The DSN code and the reason          |
| `status=bounced` with a `5.x.x` DSN code      | `HardBounce`   | `bounced`    | The DSN code and the reason          |
| `status=bounced` with a `4.x.x` DSN code      | `SoftBounce`   | `deferred`   | The DSN code and the reason          |
| `status=expired` (Postfix gave up retrying)   | `SoftBounce`   | `deferred`   | `expired, returned to sender`        |

The events go through `ApplyDeliveryEvent`, the same path as provider webhooks and bounce messages.
So the [bounce rules](bounces.md#the-rules) apply as they are: a permanent bounce suppresses the
address, soft bounces suppress it after three different messages in 30 days. A status never moves
backwards either: a late `deferred` line after `sent` changes nothing. See
[Send statuses](../campaigns/sending.md#send-statuses).

A `deferred` send that Postfix is still retrying gets no retry time, so the package never sends it
a second time, and cancelling the campaign leaves it alone because it was handed over.

The feature is optional. Without `mailing.postfix_log.path` the command does nothing and is not
scheduled.

## Configuration

```php
'postfix_log' => [
    'path' => env('MAILING_POSTFIX_LOG_PATH'),
    'reader' => FilePostfixLogReader::class,
    'queue_ttl_hours' => 48,
],
```

| Key               | Description                                                                                      |
| ----------------- | ------------------------------------------------------------------------------------------------ |
| `path`            | The log file to read, e.g. `/var/log/mailing-postfix.log`. Empty turns the feature off.          |
| `reader`          | The class bound to the `PostfixLogReader` contract. The default reads `path` incrementally.      |
| `queue_ttl_hours` | How long a Postfix queue id of one of your sends is remembered while waiting for its result.    |

With `path` set, the package schedules `mailing:read-postfix-log` every minute, with an overlap lock
of 10 minutes, unless `mailing.scheduling.enabled` is `false`.

## How the log is read

Postfix logs a message in several lines that share a queue id:

```text
Oct 21 10:15:00 vps postfix/cleanup[2202]: 7D3E21F4A2: message-id=<12.k3j4h5g6f7d8s9a0@example.org>
Oct 21 10:15:01 vps postfix/smtp[2203]: 7D3E21F4A2: to=<lid@example.org>, relay=mx.example.org[203.0.113.5]:25, delay=1.2, delays=0.1/0/0.5/0.6, dsn=2.0.0, status=sent (250 2.0.0 Ok: queued as 4F1A2B3C4D)
Oct 21 10:15:01 vps postfix/qmgr[901]: 7D3E21F4A2: removed
```

- **Message-ID first.** The `cleanup` line links the queue id to the Message-ID. Only when that
  Message-ID belongs to a send in `mailing_sends` is the queue id stored, in
  `mailing_postfix_queue_ids`. Every other line is parsed in memory and dropped.
- **Then the result.** A `to=…, status=…` line of any Postfix delivery agent (`smtp`, `lmtp`,
  `local`, `virtual`, `error`) for a stored queue id becomes a delivery event. `qmgr` lines with
  `status=expired` count too.
- **Across runs.** A message that is deferred for hours has its lines spread over many runs. The
  stored queue id survives between runs until Postfix logs `removed`, or for `queue_ttl_hours` (48).
- **Only once.** Each result is recorded in `mailing_webhook_events` under the driver `postfix-log`,
  keyed on the queue id, the status, the recipient, the DSN code and the reply. A line that is read
  twice, after a rotation or a backfill, is skipped.

Supported line formats: classic syslog (`Oct 21 10:15:01 host postfix/smtp[123]: …`), journald
`-o short` and `-o short-iso`, rsyslog's high-precision file format and RFC 5424
(`<22>1 2026-10-21T10:15:01.123+02:00 host postfix/smtp 123 - - …`). Multiple Postfix instances
(`postfix-out/smtp`) and long queue ids (`enable_long_queue_ids = yes`) work as well.

### Incremental reading and rotation

The default reader, `J87NL\Mailing\Postfix\FilePostfixLogReader`, stores the inode and the byte
position it reached per path in `mailing_postfix_log_cursors`. The next run continues there and
only reads complete lines; a line that Postfix is still writing waits for the next run.

- **Rotated file.** When the inode changed, the reader first finishes `<path>.1` from the stored
  position, when that file has the old inode, and then reads the new file from the start. So keep
  the first rotated file uncompressed (`delaycompress`), and rotate by renaming, not with
  `copytruncate`.
- **Truncated file.** When the file is shorter than the stored position, it is read from the start.
- **First run.** Without a stored position the whole file is read. That doubles as a backfill.

The position lives in the database rather than in the cache, so a `cache:clear` during a deploy
does not make the reader start over or lose its place.

## Manual and backfill runs

```bash
php artisan mailing:read-postfix-log --file=/var/log/mail.log.1
zcat /var/log/mail.log.2.gz | php artisan mailing:read-postfix-log -
```

`--file` reads a file once from the start, and `-` reads standard input. Neither touches the stored
position of the configured file. Lines that were applied before are skipped.

## Setting up the host

The app user has to read Postfix's log lines, and nothing else. Pick one of these two.

> Do not add the app user to `adm` or `systemd-journal`. Those groups can read every log on the
> server, including `auth.log` and other users' mail. A bug in the app would expose all of it.

### Option A: a dedicated log file

rsyslog copies the Postfix lines into a file of their own that the app's group may read. Postfix
keeps logging to `mail.log` as before.

```text
# /etc/rsyslog.d/30-mailing-postfix.conf
if $programname startswith 'postfix' then {
    action(type="omfile" file="/var/log/mailing-postfix.log")
}
```

Create the file with the right owner first, so rsyslog appends to it and keeps the owner:

```bash
sudo install -o syslog -g <app group> -m 0640 /dev/null /var/log/mailing-postfix.log
sudo systemctl restart rsyslog
```

Rotate it by renaming and keep `.1` uncompressed:

```text
# /etc/logrotate.d/mailing-postfix
/var/log/mailing-postfix.log {
    daily
    rotate 7
    missingok
    notifempty
    compress
    delaycompress
    create 0640 syslog <app group>
    postrotate
        /usr/lib/rsyslog/rsyslog-rotate
    endscript
}
```

Then point the app at it:

```dotenv
MAILING_POSTFIX_LOG_PATH=/var/log/mailing-postfix.log
```

`syslog` is the user rsyslog runs as on Debian and Ubuntu. On other systems use the user of your
syslog daemon, or `root`.

### Option B: journald only

Without rsyslog, root reads the journal and pipes the Postfix lines into the command, which runs as
the app user. `--cursor-file` makes journalctl continue where the previous run stopped.

```ini
# /etc/systemd/system/mailing-postfix-log.service
[Unit]
Description=Feed Postfix delivery results to the mailing package

[Service]
Type=oneshot
StateDirectory=mailing-postfix-log
ExecStart=/bin/sh -c 'journalctl --cursor-file=/var/lib/mailing-postfix-log/cursor -t postfix/cleanup -t postfix/smtp -t postfix/lmtp -t postfix/qmgr -t postfix/error -o short-iso --no-pager | runuser -u <app user> -- php /var/www/<site>/current/artisan mailing:read-postfix-log -'
```

```ini
# /etc/systemd/system/mailing-postfix-log.timer
[Unit]
Description=Feed Postfix delivery results to the mailing package every minute

[Timer]
OnCalendar=minutely
Persistent=true

[Install]
WantedBy=timers.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now mailing-postfix-log.timer
```

Leave `MAILING_POSTFIX_LOG_PATH` empty with this option: the timer replaces the schedule. The
first run, before the cursor file exists, feeds every Postfix line the journal still holds, which
doubles as a backfill. journalctl moves its cursor even when the command fails, so check the unit's
status now and then (`systemctl status mailing-postfix-log`). The `-t` identifiers are
the ones of a default Postfix; with a multi-instance setup add yours, e.g. `-t postfix-out/smtp`.

## Privacy

The log holds a line for every mail the server handles, also mail that has nothing to do with the
package. The package only stores what belongs to its own sends: the queue id of a message whose
Message-ID is in `mailing_sends`, and the result for that send. Other lines are never written
anywhere. The log file itself is yours to keep short: the logrotate example keeps seven days.

Stored queue ids are deleted when Postfix logs `removed`, or after `queue_ttl_hours`. The processed
event ids are deleted with the send records, see [Retention](../retention.md).

## Bounce messages as well

Postfix also mails a bounce message to the VERP Return-Path for a bounced or expired message. When
you read the bounce mailbox too (`mailing:process-bounces`), the same bounce can be recorded twice in
`mailing_bounce_events`, once with the source `postfix-log` and once with `dsn`. That changes no
outcome: the send status does not move backwards, a suppression is only created once, and soft
bounces are counted per message.

## Your own reader

Bind your own `J87NL\Mailing\Contracts\PostfixLogReader` with `mailing.postfix_log.reader`, for
example to read from a central log server:

```php
interface PostfixLogReader
{
    /**
     * @return iterable<string>
     */
    public function lines(): iterable;
}
```

`lines()` returns the log lines that are new since the previous call. The command only calls it when
`mailing.postfix_log.path` is set; your reader may ignore the value.
