<?php

declare(strict_types=1);

return [
    'index' => [
        'title' => 'Suppression list',
        'description' => 'Addresses that never get a mailing again, for any list.',
    ],
    'create' => [
        'title' => 'Suppress an address',
        'description' => 'The address is unsubscribed from every list and cannot sign up again while it is on the suppression list.',
    ],
    'fields' => [
        'email' => 'Email address',
        'reason' => 'Reason',
        'source' => 'Source',
        'note' => 'Note',
        'created_at' => 'Since',
    ],
    'help' => [
        'note' => 'For example why the address is suppressed.',
    ],
    'actions' => [
        'add' => 'Suppress',
        'delete_confirm' => 'Are you sure you want to remove this address from the suppression list? It can sign up again afterwards.',
    ],
    'lifted_note' => 'Suppression lifted (was: :reason)',
    'errors' => [
        'needs_settings' => 'Only the "manage sending settings" permission can remove an address from the suppression list.',
    ],
    'flash' => [
        'saved' => 'The address is on the suppression list.',
        'deleted' => 'The address has been removed from the suppression list.',
    ],
];
