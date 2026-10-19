<?php

declare(strict_types=1);

return [
    'subscription_status' => [
        'pending' => 'Wacht op bevestiging',
        'subscribed' => 'Aangemeld',
        'unsubscribed' => 'Afgemeld',
        'bounced' => 'Gebounced',
        'complained' => 'Klacht',
    ],
    'suppression_reason' => [
        'hard_bounce' => 'Harde bounce',
        'complaint' => 'Spamklacht',
        'manual' => 'Handmatig',
        'unsubscribe_all' => 'Afgemeld voor alle lijsten',
    ],
    'consent_action' => [
        'requested' => 'Aanmelding aangevraagd',
        'confirmed' => 'Aanmelding bevestigd',
        'imported' => 'Geïmporteerd',
        'unsubscribed' => 'Afgemeld',
        'suppressed' => 'Op blokkeerlijst gezet',
    ],
];
