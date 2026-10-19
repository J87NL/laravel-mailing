<?php

declare(strict_types=1);

return [
    'subscription_status' => [
        'pending' => 'Awaiting confirmation',
        'subscribed' => 'Subscribed',
        'unsubscribed' => 'Unsubscribed',
        'bounced' => 'Bounced',
        'complained' => 'Complained',
    ],
    'suppression_reason' => [
        'hard_bounce' => 'Hard bounce',
        'complaint' => 'Spam complaint',
        'manual' => 'Manual',
        'unsubscribe_all' => 'Unsubscribed from all lists',
    ],
    'consent_action' => [
        'requested' => 'Sign-up requested',
        'confirmed' => 'Sign-up confirmed',
        'imported' => 'Imported',
        'unsubscribed' => 'Unsubscribed',
        'suppressed' => 'Added to the suppression list',
    ],
];
