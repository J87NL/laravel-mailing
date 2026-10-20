# Changelog

All notable changes to this package are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.1.1]

### Fixed

- The preheader no longer uses tiny or transparent text, and test and preview mails get a non-hex Message-ID, so SpamAssassin's FONT_INVIS_MSGID no longer fires.

## [0.1.0]

### Added

- Mailing lists with double opt-in sign-up, consent records, one-click unsubscribe (RFC 8058), suppressions, CSV imports and exports, and erasure.
- Campaigns with a composer, a table-based layout with inlined CSS, a plain-text part, pre-flight checks, throttled and idempotent sending, retries, pause, resume and cancel.
- Drivers for Postfix (VERP bounces, DSN parsing) and Lettermint (signed webhooks).
- A sign-up block for `j87nl/laravel-cms`.

[0.1.1]: https://github.com/J87NL/laravel-mailing/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/J87NL/laravel-mailing/releases/tag/v0.1.0
