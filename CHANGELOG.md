# Changelog

All notable changes to this package are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

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

[0.2.1]: https://github.com/J87NL/laravel-mailing/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/J87NL/laravel-mailing/compare/v0.1.1...v0.2.0
[0.1.1]: https://github.com/J87NL/laravel-mailing/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/J87NL/laravel-mailing/releases/tag/v0.1.0
