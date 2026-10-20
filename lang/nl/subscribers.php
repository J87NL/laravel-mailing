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
    'erase' => [
        'action' => 'Gegevens verwijderen',
        'title' => ':email verwijderen',
        'description' => 'Verwijdert dit adres met al zijn aanmeldingen en de hele geschiedenis. Dit kan niet ongedaan worden gemaakt.',
        'keep_suppressed' => 'Adres op de blokkeerlijst houden',
        'keep_suppressed_help' => 'Dan krijgt het adres nooit meer een mailing, ook niet als iemand het opnieuw aanmeldt. Alleen het e-mailadres blijft bewaard.',
        'submit' => 'Definitief verwijderen',
        'suppression_note' => 'Gegevens verwijderd op verzoek',
    ],
    'flash' => [
        'unsubscribed' => 'Het adres is afgemeld.',
        'erased' => 'Het adres en zijn gegevens zijn verwijderd.',
    ],
];
