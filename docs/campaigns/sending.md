# Sending

Campaigns are sent slowly, in small portions, by a scheduled dispatcher and one queued job per
recipient.

## The dispatcher

`mailing:dispatch` runs every minute (`J87NL\Mailing\Sending\Dispatcher`). Each run:

1. **Fails stale sends.** A send that has been `sending` for longer than
   `mailing.sending.stale_minutes` (30) becomes `failed` with the response `interrupted`.
2. **Starts due campaigns.** A `scheduled` campaign whose time has come gets one send row per
   recipient and becomes `sending`.
3. **Releases sends.** For every `sending` campaign, oldest first, it takes up to
   `throttle_per_minute × mailing.sending.scan_factor` sends that are due: `queued` sends, and
   `deferred` sends whose retry time has come. For each it tries to claim a throttle slot and, when
   that succeeds, dispatches a `SendCampaignMessage` job.
4. **Finishes campaigns.** A `sending` campaign without queued, sending or retryable deferred sends
   becomes `sent`.
5. **Prunes** throttle counters older than two days.

Paused campaigns are skipped in step 3. Resuming them continues where they stopped.

## The recipient snapshot

When a campaign starts, it gets a send row for every subscription of the list that is `subscribed`,
has a `confirmed_at` and whose address is not suppressed. Later sign-ups do not join a running
campaign. Each subscription gets at most one send per campaign.

## Throttles

A send is only released when all of these have room:

| Limit                 | Window                          | Set by                                    |
| --------------------- | ------------------------------- | ----------------------------------------- |
| Per list per minute   | The current minute              | `throttle_per_minute` of the list         |
| Per list per day      | The day in `mailing.timezone`   | `daily_cap` of the list (none when empty) |
| Per domain per minute | The current minute, all lists   | `mailing.sending.domain_limits`           |

The counters live in the database (`mailing_rate_counters`), so they hold across workers and
servers. The daily cap resets at midnight in `mailing.timezone`.

The campaign page estimates the finish time from the remaining sends, the throttle and the daily
cap.

## The send job

`J87NL\Mailing\Jobs\SendCampaignMessage` runs on the `mailing.sending.queue` queue (default `mailing`)
and is tried once. It:

1. Stops when the campaign is no longer `sending`, or when the minute its throttle slot was claimed
   for has passed. The slot is then given back, and the dispatcher releases the send again later.
   A worker that stalls and comes back never sends a burst of old jobs.
2. Claims the send row atomically: only a `queued` or `deferred` row with the expected number of
   attempts becomes `sending`. A duplicate job does nothing.
3. Stores a new Message-ID on the row: `<send id>.<random>@<list id domain>`.
4. Skips the send (`skipped`, response `skipped_unsubscribed`) when the subscription is no longer
   `subscribed` or the address has been suppressed.
5. Lets the driver of the list mark the message and sends it through the mailer of the list.

| Outcome                                                  | Send status   |
| -------------------------------------------------------- | ------------- |
| The mailer accepted the message                          | `handed_over` |
| A temporary transport error and a retry left             | `deferred`, retried after the next delay in `mailing.sending.retry_minutes` |
| A temporary transport error and no retry left            | `failed`      |
| A final transport error                                  | `failed`      |
| A transport error without a code                         | `failed`, response `interrupted` |
| Any other exception                                      | `failed`      |

A transport error without a code fails the send instead of retrying it, because the mail may
have been accepted before the connection broke. The default retry delays are 15 minutes, 1 hour and
4 hours.

Whether an error is temporary depends on the kind of code, recognised by
`J87NL\Mailing\Mail\TransportFailure::isTemporary()` in the same way as the cause of a failed
test send:

| Transport                         | Temporary              | Final                    |
| --------------------------------- | ---------------------- | ------------------------ |
| SMTP reply code                   | 4xx                    | 5xx and any other code   |
| HTTP status of a mail API         | 408, 429 and 5xx       | every other 4xx and any other code |

A 5xx of a mail API can come after the API already accepted the message, so a retry can deliver it
twice. The Lettermint driver sends an `Idempotency-Key` per send, so Lettermint can recognise the
retry; a driver for another HTTP transport without such a key carries that risk.

See [Lettermint](../delivery/lettermint.md#errors-from-the-api).

## Send statuses

`J87NL\Mailing\Enums\SendStatus`:

| Status        | Meaning                                                           |
| ------------- | ----------------------------------------------------------------- |
| `queued`      | Waiting for a throttle slot                                       |
| `sending`     | Claimed by a job                                                  |
| `handed_over` | Accepted by the mailer                                            |
| `deferred`    | Temporary failure or soft bounce                                  |
| `delivered`   | Delivery reported by the provider                                 |
| `bounced`     | Hard bounce, or the soft bounce that suppressed the address       |
| `complained`  | Spam complaint                                                    |
| `failed`      | Permanent failure, interrupted or stale                           |
| `skipped`     | Not sent: unsubscribed or suppressed in the meantime              |
| `cancelled`   | Not sent: the campaign was cancelled                              |

Delivery events never move a send backwards. Every status has a rank (`SendStatus::rank()`), and
`Send::advanceTo()` only changes a send to a status of equal or higher rank:
`complained` > `bounced` > `delivered` > `failed` > `deferred` > `handed_over` > the rest. A late
"delivered" event cannot undo a bounce.

## Pause, resume and cancel

- **Pause** stops releasing sends. Jobs already dispatched see the status and give back their slot.
- **Resume** continues releasing.
- **Cancel** cancels every `queued` and `deferred` send. Sends that were handed over stay as they
  are.

## Retrying failed sends

"Retry failed sends" puts every `failed` send that was never handed over back to `queued`. It is
available for `sending`, `paused` and `sent` campaigns. A `sent` campaign becomes `sending` again.

Interrupted and stale sends are only retried this way, by an admin, because they may already have
been delivered.

## Starting a campaign from code

`J87NL\Mailing\Actions\StartCampaign::handle(Campaign $campaign): bool` takes the recipient snapshot
and sets the campaign to `sending`. It only starts a `draft` or `scheduled` campaign. It does not run
the pre-flight checks.
