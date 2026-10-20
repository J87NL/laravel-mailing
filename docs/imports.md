# Imports

A CSV import adds existing addresses to a list. Imports are made at `/admin/mailing/imports` and
need the `mailing.subscribers` permission. At least one list must exist.

## The import form

| Field            | Description                                                                              |
| ---------------- | ---------------------------------------------------------------------------------------- |
| `list_id`        | The list to import into.                                                                 |
| `file`           | A `.csv` or `.txt` file of at most `mailing.import.max_kilobytes` (default 1024 KB).     |
| `consent_source` | Required. Where the addresses come from, for example "paper sign-up sheet at the May market". Up to 200 characters. |
| `consent_note`   | Optional. Fill it in only when consent is documented elsewhere.                          |

The consent source is stored on every subscription and consent record as `import: <source>`.

## With or without a consent note

**Without a consent note** every new address becomes `pending` and gets a confirmation mail with
its own wording ("Would you like to receive the mailings of ...?"). Only addresses that confirm
receive campaigns.

**With a consent note** every new address becomes `subscribed` at once, without a confirmation
mail. The note is stored on the `imported` consent record.

## The file

- The delimiter is a semicolon when the first line has more semicolons than commas, otherwise a
  comma.
- A header row is optional. When the first row contains `email`, `e-mail`, `emailadres`,
  `e-mailadres` or `mail` (case insensitive), it is a header, and the name column is `name` or
  `naam` when present.
- Without a header the first column is the email address and the second the name.
- A UTF-8 byte order mark is ignored.
- Names are cut at 120 characters.
- Only the first `mailing.import.max_rows` data rows (default 5000) are processed. The import then
  reports that the file had more rows.

## What happens per row

| Row                                                      | Result                                         |
| -------------------------------------------------------- | ---------------------------------------------- |
| Empty                                                    | Ignored                                        |
| Invalid email address                                    | Skipped, reported with its line number         |
| Duplicate within the file                                | Skipped                                        |
| Suppressed address                                       | Skipped                                        |
| Address with an existing subscription on the list        | Skipped, unless it is pending and never got a confirmation mail; then it is imported again and gets one |
| New address                                              | Imported                                       |

The subscriber is created when needed, and gets the name from the file when it has none.

A row that throws an exception is reported ("Line 12 could not be processed") and the import
continues. When queueing a confirmation mail fails, the row is reported and its subscription is
left without a sent confirmation, so a later import of the same file sends it.

## Spreading the confirmation mails

Confirmation mails of one import are queued with a delay, so no more than
`mailing.import.confirmations_per_minute` (default 20) go out per minute.

## Results

Every import is stored in `mailing_imports` (`J87NL\Mailing\Models\MailingImport`) with the file
name, the consent source and note, the counts `imported`, `skipped` and `confirmations_sent`, up to
50 error messages and the acting admin. The imports index lists them. After an import the admin
sees the counts as a toast and the errors above the table.

## Importing from code

```php
use J87NL\Mailing\Actions\ImportSubscribers;

$import = app(ImportSubscribers::class)->handle(
    $list,
    storage_path('app/import.csv'),
    'import.csv',
    'Paper sign-up sheet, May market',
    null,
    $user->id,
);
```

The signature is
`handle(MailingList $list, string $path, string $filename, string $consentSource, ?string $consentNote, ?int $actorId): MailingImport`.
