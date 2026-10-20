<?php

declare(strict_types=1);

return [
    'index' => [
        'title' => 'Mailinglijsten',
        'description' => 'Lijsten waarop bezoekers zich met dubbele bevestiging aanmelden.',
    ],
    'create' => [
        'title' => 'Nieuwe lijst',
    ],
    'edit' => [
        'title' => 'Lijst :name bewerken',
    ],
    'transport' => [
        'title' => 'Verzending van :name',
        'description' => 'Welke mailer en afzender deze lijst gebruikt, en hoe snel er verstuurd wordt. Afzender en verzendroute staan los van elkaar.',
    ],
    'fields' => [
        'name' => 'Naam',
        'key' => 'Sleutel',
        'description' => 'Omschrijving',
        'reply_to' => 'Antwoordadres',
        'is_public' => 'Openbaar',
        'subscribed' => 'Aangemeld',
        'pending' => 'Wacht op bevestiging',
        'mailer' => 'Mailer',
        'sender_identity' => 'Afzender',
        'throttle_per_minute' => 'Mails per minuut',
        'daily_cap' => 'Maximum per dag',
    ],
    'help' => [
        'key' => 'Kleine letters, cijfers en streepjes. Staat in de aanmeld-URL en in de List-Id.',
        'description' => 'Staat boven het aanmeldformulier.',
        'reply_to' => 'Antwoorden op mails van deze lijst gaan naar dit adres.',
        'is_public' => 'Alleen openbare lijsten hebben een aanmeldpagina en kunnen in een aanmeldblok.',
        'mailer' => 'De Laravel-mailer uit config/mail.php waarlangs mailings van deze lijst gaan.',
        'sender_identity' => 'Het afzenderadres van deze lijst.',
        'throttle_per_minute' => 'Postfix: 20 is een veilige standaard.',
        'daily_cap' => 'Leeg laten voor geen maximum.',
        'key_locked' => 'De sleutel staat in de webadressen, de List-Id van elke mail en in de aanmeldblokken op je pagina\'s. Daarom kun je hem na het aanmaken niet meer wijzigen.',
    ],
    'actions' => [
        'transport' => 'Verzending',
        'signup_page' => 'Aanmeldpagina',
        'delete_confirm' => 'Weet je zeker dat je deze lijst wilt verwijderen?',
    ],
    'errors' => [
        'needs_settings' => 'Hiervoor heb je het recht "verzendinstellingen beheren" nodig.',
        'has_subscriptions' => 'Deze lijst heeft abonnees en kan niet worden verwijderd.',
        'no_sender' => 'Er is nog geen afzender ingesteld. Vul MAILING_FROM_ADDRESS of MAIL_FROM_ADDRESS in en probeer het opnieuw.',
    ],
    'flash' => [
        'saved' => 'De lijst is opgeslagen.',
        'deleted' => 'De lijst is verwijderd.',
    ],
];
