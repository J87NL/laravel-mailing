<?php

declare(strict_types=1);

return [
    'index' => [
        'title' => 'Abonnees',
        'description' => 'Iedereen die zich ooit heeft aangemeld, met de geschiedenis per adres.',
    ],
    'show' => [
        'subscriptions' => 'Lijsten',
        'history' => 'Geschiedenis',
        'no_history' => 'Nog geen geschiedenis.',
        'suppressed' => 'Dit adres staat op de blokkeerlijst (:reason, sinds :date) en ontvangt geen mailings.',
        'created_at' => 'Bekend sinds :date',
    ],
    'fields' => [
        'email' => 'E-mailadres',
        'name' => 'Naam',
        'list' => 'Lijst',
        'subscriptions' => 'Lijsten',
        'status' => 'Status',
        'consent_source' => 'Bron van toestemming',
        'confirmed_at' => 'Bevestigd op',
        'unsubscribed_at' => 'Afgemeld op',
        'created_at' => 'Aangemaakt',
        'action' => 'Actie',
        'source' => 'Bron',
        'ip' => 'IP-adres',
        'note' => 'Notitie',
        'at' => 'Moment',
    ],
    'actions' => [
        'show' => 'Bekijken',
        'export' => 'Exporteren als CSV',
        'unsubscribe' => 'Afmelden',
        'unsubscribe_confirm' => 'Weet je zeker dat je dit adres van deze lijst wilt afmelden?',
    ],
    'flash' => [
        'unsubscribed' => 'Het adres is afgemeld.',
    ],
];
