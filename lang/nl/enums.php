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
        'soft_bounces' => 'Herhaaldelijk soft bounce',
    ],
    'consent_action' => [
        'requested' => 'Aanmelding aangevraagd',
        'confirmed' => 'Aanmelding bevestigd',
        'imported' => 'Geïmporteerd',
        'unsubscribed' => 'Afgemeld',
        'suppressed' => 'Op blokkeerlijst gezet',
        'unsuppressed' => 'Van blokkeerlijst gehaald',
    ],
    'campaign_status' => [
        'draft' => 'Concept',
        'scheduled' => 'Ingepland',
        'sending' => 'Wordt verstuurd',
        'paused' => 'Gepauzeerd',
        'sent' => 'Verstuurd',
        'cancelled' => 'Geannuleerd',
    ],
    'send_status' => [
        'queued' => 'In de wachtrij',
        'sending' => 'Bezig',
        'handed_over' => 'Overgedragen',
        'deferred' => 'Uitgesteld',
        'delivered' => 'Afgeleverd',
        'bounced' => 'Gebounced',
        'complained' => 'Klacht',
        'failed' => 'Mislukt',
        'skipped' => 'Overgeslagen',
        'cancelled' => 'Geannuleerd',
    ],
    'bounce_type' => [
        'hard' => 'Hard',
        'soft' => 'Soft',
    ],
];
