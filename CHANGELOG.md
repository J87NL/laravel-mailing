# Changelog

All notable changes to this package are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- Campaign attachments: add PDF, JPG or PNG files in the composer (types, a 10 MB total and the private disk are configurable under `mailing.attachments`). They can only change while the campaign is a draft, and go along with every campaign mail and test mail.
- Per attachment, "send as download link": the mail gets a Downloads block with a temporary signed URL (30 days) served by the package at `/mailing/attachments/{token}`, without session or cookie. Real attachments are the default.
- The pre-flight check gets an `attachments` item that warns when the real attachments exceed 2 MB together, or when a mail with an attachment has less than 200 characters of text.
- `mailing:prune` deletes the attachments of sent and cancelled campaigns 30 days after they finished, never before their links expire. Deleting a draft deletes its files. A new migration adds `mailing_campaign_attachments`.

## [0.2.3] - 2026-10-21

### Added

- Optional delivery results from Postfix's own log. With `mailing.postfix_log.path` (`MAILING_POSTFIX_LOG_PATH`) set, `mailing:read-postfix-log` runs every minute, reads the new lines of the log, follows rotation to `<path>.1` and moves a Postfix send from `handed_over` to `delivered`, `deferred` or `bounced` through the same rules as webhooks and bounce messages. A message that expired in the Postfix queue counts as a soft bounce and fails the send. Only lines of the package's own sends, matched by Message-ID, are stored. `--file` and `-` read a file or standard input once, for backfills and journald-only hosts. Two new tables: `mailing_postfix_log_cursors` and `mailing_postfix_queue_ids`. See `docs/delivery/postfix.md` for the host setup.
- A `Delivered` event stores the reply of the receiving server, when the event carries one, as the last response of the send. The send list on the campaign page shortens long responses to 160 characters.

### Changed

- Cancelling a campaign no longer cancels a `deferred` send that was handed over: the provider or Postfix is still retrying it, so it can still be delivered.

## [0.2.2] - 2026-10-21

### Added

- `mailing.signup.privacy_url` (`MAILING_PRIVACY_URL`): the sign-up form links to this privacy statement. It accepts a local path starting with one `/` (with query or fragment) or an `http`/`https` URL with a host.

### Fixed

- The sending job tells SMTP codes and HTTP statuses apart, as the test send already did: for a mail API such as Lettermint, 408, 429 and 5xx are retried and every other 4xx fails at once. Before, a 401 or 422 was retried three times and a 503 failed at once.
- The test-send route refuses a campaign that is no longer a draft, also when the request does not come from the campaign page.
- Lifting a suppression through a provider webhook normalises the address the same way as adding it, so an address with capitals outside ASCII or surrounding spaces is found.
- `mailing_bounce_events.source` is widened to 255 characters by a new migration, so the webhook source of a driver key longer than 11 characters fits.

## [0.2.1] - 2026-10-21

### Changed

- The subscriber list starts filtered on "subscribed". Other statuses show when you pick them or "All statuses", and with a list chosen the status applies to the subscription on that list.

## [0.2.0] - 2026-10-21

### Changed

- The host menu gets one "Mailing" item that opens the first mailing page the user may see, and the mailing pages share a submenu with campaigns, lists, subscribers, suppressions and imports, each gated on its own permission.
- The confirmation page submits itself with a small same-origin script, so one click in the mail is enough. Without JavaScript the button stays, and a GET still never confirms. Mail scanners that run JavaScript can confirm too: an accepted trade-off.

### Fixed

- A failed test send no longer gives a 500. It shows why the mail server refused it, for SMTP as well as for HTTP API transports such as Lettermint.

## [0.1.1]

### Fixed

- The preheader no longer uses tiny or transparent text, and test and preview mails get a non-hex Message-ID, so SpamAssassin's FONT_INVIS_MSGID no longer fires.

## [0.1.0]

### Added

- Mailing lists with double opt-in sign-up, consent records, one-click unsubscribe (RFC 8058), suppressions, CSV imports and exports, and erasure.
- Campaigns with a composer, a table-based layout with inlined CSS, a plain-text part, pre-flight checks, throttled and idempotent sending, retries, pause, resume and cancel.
- Drivers for Postfix (VERP bounces, DSN parsing) and Lettermint (signed webhooks).
- A sign-up block for `j87nl/laravel-cms`.

[0.2.3]: https://github.com/J87NL/laravel-mailing/compare/v0.2.2...v0.2.3
[0.2.2]: https://github.com/J87NL/laravel-mailing/compare/v0.2.1...v0.2.2
[0.2.1]: https://github.com/J87NL/laravel-mailing/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/J87NL/laravel-mailing/compare/v0.1.1...v0.2.0
[0.1.1]: https://github.com/J87NL/laravel-mailing/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/J87NL/laravel-mailing/releases/tag/v0.1.0
