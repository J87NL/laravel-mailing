<?php

declare(strict_types=1);

return [
    'index' => [
        'title' => 'Imports',
        'description' => 'Put addresses from a CSV file on a list, always with a consent source.',
    ],
    'create' => [
        'title' => 'Import addresses',
        'description' => 'A CSV with an email column and optionally name. Imported addresses get one confirmation mail, unless you record where earlier consent is documented.',
    ],
    'fields' => [
        'list' => 'List',
        'file' => 'CSV file',
        'consent_source' => 'Consent source',
        'consent_note' => 'Where is the consent documented?',
        'imported' => 'Imported',
        'skipped' => 'Skipped',
        'confirmations_sent' => 'Confirmation mails',
        'created_at' => 'When',
    ],
    'help' => [
        'file' => 'Columns email (or e-mailadres) and name (or naam), separated by comma or semicolon. A header row is optional.',
        'consent_source' => 'For example "paper sign-up sheet at the May 2026 market".',
        'consent_note' => 'Only fill this in when consent is documented elsewhere. The addresses are then subscribed without a confirmation mail.',
    ],
    'actions' => [
        'import' => 'Import',
        'new' => 'New import',
    ],
    'errors' => [
        'invalid_email' => 'Line :line: not a valid email address.',
        'too_many_rows' => 'The file has more than :max rows; only the first :max were processed.',
        'row_failed' => 'Line :line could not be processed.',
        'mail_failed' => 'Line :line was stored, but its confirmation mail could not be sent. Import the file again to send that mail.',
        'no_lists' => 'Create a mailing list first.',
    ],
    'flash' => [
        'imported' => ':imported imported, :skipped skipped, :confirmations confirmation mails sent.',
    ],
];
