<?php

declare(strict_types=1);

return [
    'index' => [
        'title' => 'Subscribers',
        'description' => 'Everyone who ever signed up, with the history per address.',
    ],
    'show' => [
        'subscriptions' => 'Lists',
        'history' => 'History',
        'no_history' => 'No history yet.',
        'suppressed' => 'This address is on the suppression list (:reason, since :date) and receives no mailings.',
        'created_at' => 'Known since :date',
    ],
    'filters' => [
        'all_statuses' => 'All statuses',
    ],
    'fields' => [
        'email' => 'Email address',
        'name' => 'Name',
        'list' => 'List',
        'subscriptions' => 'Lists',
        'status' => 'Status',
        'consent_source' => 'Consent source',
        'confirmed_at' => 'Confirmed at',
        'unsubscribed_at' => 'Unsubscribed at',
        'created_at' => 'Created',
        'action' => 'Action',
        'source' => 'Source',
        'ip' => 'IP address',
        'note' => 'Note',
        'at' => 'When',
    ],
    'actions' => [
        'export' => 'Export as CSV',
        'unsubscribe' => 'Unsubscribe',
        'unsubscribe_confirm' => 'Are you sure you want to unsubscribe this address from this list?',
    ],
    'erase' => [
        'action' => 'Erase data',
        'title' => 'Erase :email',
        'description' => 'Deletes this address with all its subscriptions and its whole history. This cannot be undone.',
        'keep_suppressed' => 'Keep the address on the suppression list',
        'keep_suppressed_help' => 'The address then never gets a mailing again, even when someone signs it up again. Only the email address is kept.',
        'submit' => 'Erase permanently',
        'suppression_note' => 'Data erased on request',
    ],
    'flash' => [
        'unsubscribed' => 'The address has been unsubscribed.',
        'erased' => 'The address and its data have been erased.',
    ],
];
