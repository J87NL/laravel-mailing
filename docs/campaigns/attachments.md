# Attachments

A campaign can carry one or more files. They are added, removed and switched between "attachment"
and "download link" in the composer, so only while the campaign is a draft and only with
`mailing.campaigns.edit`. Once a campaign is scheduled, the edit page and the update route refuse
it with the `campaign` error that names its status, and its attachments stay as they are.
Unscheduling makes it a draft again.

## Storage and limits

```php
'attachments' => [
    'disk' => env('MAILING_ATTACHMENTS_DISK', 'local'),
    'directory' => 'mailing/attachments',
    'extensions' => ['pdf', 'jpg', 'png'],
    'max_total_kilobytes' => 10240,
    'warn_total_kilobytes' => 2048,
    'min_text_characters' => 200,
    'default_as_link' => false,
    'link_valid_days' => 30,
    'retention_days' => 30,
],
```

- Files go on `disk`, under `directory/<campaign id>/<random token>.<extension>`. Use a private
  disk: the `local` disk of a Laravel 11+ app is `storage/app/private`. Never point it at the
  `public` disk; the files are only meant to be reached through the mail or the signed link.
- `extensions` lists the allowed types. Both the extension of the uploaded name and the type
  detected from the content must be in the list (`jpg` also allows `jpeg`), so a renamed web page
  or program is refused.
- All attachments of a campaign together may not exceed `max_total_kilobytes` (10 MB). The files
  already attached count, minus the ones removed in the same save.
- The original name is kept for the recipient, cleaned of control characters and slashes, with
  the extension of the detected type. A real PNG named `plan.pdf` is stored as `plan.png`.
- The total is checked again inside the locked save, so two admins saving at the same moment cannot
  pass the limit together.

### Upload limits of PHP and the web server

The package limit only applies to what reaches PHP. For 10 MB of attachments in one save, raise:

- `upload_max_filesize` to at least the largest single file (10M), and `post_max_size` to the total
  plus the rest of the form (for example 12M), in the `php.ini` of PHP-FPM;
- the request body limit of the web server, such as `client_max_body_size 12m;` in nginx.

Otherwise a large upload is cut off before validation and the composer shows a generic error, or
the server answers 413.

## Attachment or download link

Each file goes either as a real attachment or as a download link:

- **Attachment:** the file is part of every campaign mail and test mail.
- **Download link:** the mail gets a "Downloads" block, in the HTML and in the plain-text part,
  with a signed link per file and the date the links stop working.

New files follow the toggle "Send new attachments as a download link" (default from
`default_as_link`). Files already attached are switched with the checkboxes under it.

The default is a **real attachment**. A flyer or a programme of a few hundred kilobytes is what a
rommelmarkt mailing usually carries, and recipients expect it in the mail itself, also offline and
after the link would have expired. A link also only works while the file is kept (see below). The
cost of attachments is delivery: large mails are throttled or filtered by mailbox providers. The
[pre-flight check](preflight.md#attachments) warns above `warn_total_kilobytes` and advises a
download link for large files, and only real attachments count towards that warning.

## The download route

| Method | URL                               | Name                           | Rate limit    |
| ------ | --------------------------------- | ------------------------------ | ------------- |
| GET    | `/mailing/attachments/{token}`    | `mailing.attachments.download` | 60 per minute |

- The URL is a temporary signed URL (`URL::temporarySignedRoute`), valid for `link_valid_days`
  (30) days from the moment the mail is rendered. A missing, altered or expired signature gives a
  403.
- `token` is a random 40-character string per attachment, not the database id.
- The route runs with `mailing.routes.public_middleware` only, like the other public routes: no
  session, no cookie, no CSRF.
- The response is a download (`Content-Disposition: attachment`) with the stored type,
  `X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store`,
  `Referrer-Policy: no-referrer` and `X-Robots-Tag: noindex, nofollow`.
- A deleted or pruned attachment gives a 404.
- The route stays free of session and cookies even when `mailing.routes.public_middleware` contains
  `web`: it removes `EncryptCookies`, `AddQueuedCookiesToResponse`, `StartSession`,
  `ShareErrorsFromSession` and `PreventRequestForgery` (and their subclasses) for itself.

The links are absolute URLs signed against `APP_URL` and the request scheme. Behind a proxy that
ends TLS, set `APP_URL` to the `https://` address and trust the proxy (`TrustProxies`, or
`$middleware->trustProxies(...)` in `bootstrap/app.php`). Otherwise the mail carries `http://`
links, or the signature of an `https://` link fails with a 403 because the app sees `http`.

Everyone who has the mail can open the link, also when it is forwarded. Do not attach anything
that only some recipients may see.

## A missing file

When the file of an attachment is gone from the disk (deleted by hand, a disk that was not shared
between servers), the [pre-flight check](preflight.md#attachments) turns red and names the file, so
the campaign cannot be scheduled. The spam score is then not measured. A campaign that is already
sending fails each send with the cause "The file of attachment ... is missing" instead of sending a
mail without it. Remove the attachment and add it again.

## Retention

- Deleting a draft deletes its attachments and their files.
- Removing an attachment in the composer deletes its file.
- `mailing:prune` deletes the attachments of campaigns that are `sent` or `cancelled` once
  `finished_at` is older than `retention_days` (30) days. The period is never shorter than
  `link_valid_days`, so a link in the last mail of a campaign keeps working until it expires.
- Files are deleted after the database transaction commits.
- Pruning sets `attachments_pruned_at` on the campaign without changing its `updated_at`. From then
  on "Retry failed sends" is disabled with the reason, and the route refuses it, because a retried
  mail would go out without its attachments. The campaign page shows the date.

## Cost of the pre-flight spam check

The spam score is measured on the real message, so the real attachments are part of the raw mail
sent to rspamd. With 10 MB of attachments that is a 10 MB request each time the pre-flight runs
(at most once per 10 minutes per campaign version, and when scheduling). That is on purpose: the
attachments are part of what spam filters judge.

## Not over MCP

There is no MCP tool to upload attachments.
