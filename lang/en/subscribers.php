<?php

declare(strict_types=1);

return [
    'index' => [
        'title' => 'Subscribers',
        'description' => 'Everyone who ever signed up, with the history per address.',
    ],
    'create' => [
        'title' => 'Add subscriber',
        'description' => 'Puts an address on a list directly, without a confirmation mail. Only do this when you can show that the address gave consent.',
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
        'consent_note' => 'Why may this address receive mail?',
    ],
    'help' => [
        'consent_source' => 'For example "signed up at the counter" or "request by mail of 12 May".',
        'consent_note' => 'Record how and when the consent was given. This goes into the history of the address.',
    ],
    'actions' => [
        'add' => 'Add subscriber',
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
    'errors' => [
        'no_lists' => 'Create a mailing list first.',
        'suppressed' => 'This address is on the suppression list and cannot be subscribed.',
        'left_list' => 'This address was unsubscribed or removed from this list before. Only the subscriber can sign up again, through the sign-up form.',
    ],
    'flash' => [
        'added' => 'The address has been subscribed.',
        'already_subscribed' => 'This address was already on this list. Nothing was changed.',
        'unsubscribed' => 'The address has been unsubscribed.',
        'erased' => 'The address and its data have been erased.',
    ],
];
