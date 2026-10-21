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
  the extension of the detected type.

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

Everyone who has the mail can open the link, also when it is forwarded. Do not attach anything
that only some recipients may see.

## Retention

- Deleting a draft deletes its attachments and their files.
- Removing an attachment in the composer deletes its file.
- `mailing:prune` deletes the attachments of campaigns that are `sent` or `cancelled` once
  `finished_at` is older than `retention_days` (30) days. The period is never shorter than
  `link_valid_days`, so a link in the last mail of a campaign keeps working until it expires.
- Files are deleted after the database transaction commits.

## Not over MCP

There is no MCP tool to upload attachments.
