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
        'show' => 'View',
        'export' => 'Export as CSV',
        'unsubscribe' => 'Unsubscribe',
        'unsubscribe_confirm' => 'Are you sure you want to unsubscribe this address from this list?',
    ],
    'flash' => [
        'unsubscribed' => 'The address has been unsubscribed.',
    ],
];
