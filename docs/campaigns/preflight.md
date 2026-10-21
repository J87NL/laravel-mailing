# Pre-flight checks

Before a campaign can be scheduled, `J87NL\Mailing\Preflight\PreflightCheck` checks the sending
domain, the content and the links. Each check is green (`ok`), orange (`warning`) or red (`error`).
A red item blocks scheduling; orange items do not.

The campaign page shows the checks for drafts. They are cached for ten minutes per version of the
campaign. Scheduling runs them again without the cache.

## The checks

The domain checks use the domain of the From address of the list, and the driver of the list
(see [Drivers](../delivery/drivers.md)).

### SPF

Looks up the TXT record starting with `v=spf1`.

- **Postfix with a bounce domain** checks the SPF record of `mailing.bounces.domain`, because the
  VERP Return-Path is on that domain. Otherwise it checks the From domain.
- Red when there is no SPF record.
- Red when the record does not contain every entry of `mailing.preflight.spf.<driver>`, for example
  `ip4:203.0.113.10` (`MAILING_SPF_POSTFIX`). The comparison is a case-insensitive text match.

### DKIM

Looks up `<selector>._domainkey.<from domain>` for every selector in
`mailing.preflight.dkim_selectors.<driver>` (`MAILING_DKIM_SELECTORS_POSTFIX`).

- Orange when no selectors are configured, which is the default for Lettermint.
- Red when a selector has no record.

### DMARC

Red when `_dmarc.<from domain>` has no `v=DMARC1` record.

### Alignment

- Orange for a driver other than Postfix: the provider handles alignment.
- Postfix without a bounce domain: green, the Return-Path is the From domain.
- Postfix with a bounce domain: green when it is the From domain or a subdomain of it, red otherwise.

### Blocklists (Postfix only)

Looks up every IPv4 address in `mailing.preflight.sending_ips` (`MAILING_SENDING_IPS`) in every zone
of `mailing.preflight.blocklists`.

- Orange when no sending IPs are configured.
- Red when an address is listed (an answer in `127.0.0.0/8`).
- Orange when a zone refused to answer (an answer in `127.255.255.0/24`), for example because your
  resolver is blocked by that list.

### Spam score

Renders the real message, the way a [test send](composing.md#test-sends) would be, and scores it
with the `SpamScorer` contract. The default `RspamdSpamScorer` posts the message to
`<mailing.preflight.rspamd.url>/checkv2`, with the `Password` header when
`mailing.preflight.rspamd.password` is set.

- Orange when no score is available: no Rspamd URL, a connection error or an unsuccessful answer.
- Red when the score is at or above `mailing.preflight.spam_threshold` (5.0).

### Links

Checks every `href` in the sanitized body, except `mailto:` and `tel:` links. For each link:

- It must be an `https` URL, on the default port or port 443, without credentials. Anything else
  is red.
- Every IPv4 address the host resolves to must be public. A host without an A record, or with one
  in a private, reserved or loopback range, is red. The request is pinned to the checked address,
  so DNS cannot change between the check and the request.
- A `HEAD` request is sent (a `GET` when the server answers `405`). Redirects are followed up to
  three times. A redirect target on a non-public address is red; a redirect target that is not a
  valid `https` URL stops the check, and the link is orange.
- A final answer other than `200`, a redirect chain that does not end, or no answer at all, is
  orange.

All links together get `mailing.preflight.link_budget_seconds` (20). Links that are not reached
within that time are orange.

### Text

Red when the visible text of the body is shorter than 20 characters, or the plain-text part is
empty.

### Attachments

Only shown when the campaign has [attachments](attachments.md). It never blocks scheduling.

- **Warning** when the real attachments (not the download links) are together larger than
  `mailing.attachments.warn_total_kilobytes` (2 MB). Large mails hurt delivery; send large files as
  a download link.
- **Warning** when the campaign has a real attachment and the visible text of the body is shorter
  than `mailing.attachments.min_text_characters` (200). A mail that is mostly an attachment with a
  line like "see the attachment" looks like spam to filters.
- **Green** otherwise, with the total size of the real attachments.

Adding, removing or switching an attachment touches the campaign, so the cached result is
refreshed.

## Results

`run(Campaign $campaign): array` and `cached(Campaign $campaign): array` return a list of
`J87NL\Mailing\Preflight\PreflightItem` (excerpt; `blocks()` and `toArray()` are left out):

```php
final readonly class PreflightItem
{
    public const string OK = 'ok';
    public const string WARNING = 'warning';
    public const string ERROR = 'error';

    public function __construct(
        public string $key,
        public string $status,
        public string $message,
    ) {}
}
```

The keys are `spf`, `dkim`, `dmarc`, `alignment`, `blocklists`, `spam_score`, `links`, `text` and,
when the campaign has attachments, `attachments`.
`PreflightCheck::blocks($items)` returns `true` when any item is red. The labels and messages come
from `mailing::preflight`.

## Replacing the lookups

DNS lookups go through the `DnsResolver` contract and the spam score through `SpamScorer`. Bind your
own through `mailing.preflight.dns_resolver` and `mailing.preflight.spam_scorer`. The package also
ships `J87NL\Mailing\Preflight\NullSpamScorer`, which never returns a score. See
[Contracts](../contracts.md).
