<?php

declare(strict_types=1);

return [
    'index' => [
        'title' => 'Abonnees',
        'description' => 'Iedereen die zich ooit heeft aangemeld, met de geschiedenis per adres.',
    ],
    'create' => [
        'title' => 'Abonnee toevoegen',
        'description' => 'Zet een adres direct op een lijst, zonder bevestigingsmail. Doe dit alleen als je kunt aantonen dat het adres toestemming heeft gegeven.',
    ],
    'show' => [
        'subscriptions' => 'Lijsten',
        'history' => 'Geschiedenis',
        'no_history' => 'Nog geen geschiedenis.',
        'suppressed' => 'Dit adres staat op de blokkeerlijst (:reason, sinds :date) en ontvangt geen mailings.',
        'created_at' => 'Bekend sinds :date',
    ],
    'filters' => [
        'all_statuses' => 'Alle statussen',
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
        'consent_note' => 'Waarom mag dit adres mail ontvangen?',
    ],
    'help' => [
        'consent_source' => 'Bijvoorbeeld "aanmelding aan de kassa" of "verzoek per mail van 12 mei".',
        'consent_note' => 'Leg vast hoe en wanneer de toestemming is gegeven. Dit komt in de geschiedenis van het adres.',
    ],
    'actions' => [
        'add' => 'Abonnee toevoegen',
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
    'errors' => [
        'no_lists' => 'Maak eerst een mailinglijst aan.',
        'suppressed' => 'Dit adres staat op de blokkeerlijst en kan niet worden aangemeld.',
        'left_list' => 'Dit adres is eerder van deze lijst afgemeld of eraf gehaald. Alleen de abonnee zelf kan zich opnieuw aanmelden, via het aanmeldformulier.',
    ],
    'flash' => [
        'added' => 'Het adres is aangemeld.',
        'already_subscribed' => 'Dit adres stond al op deze lijst. Er is niets gewijzigd.',
        'unsubscribed' => 'Het adres is afgemeld.',
        'erased' => 'Het adres en zijn gegevens zijn verwijderd.',
    ],
];
