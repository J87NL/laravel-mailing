<?php

declare(strict_types=1);

return [
    'index' => [
        'title' => 'Blokkeerlijst',
        'description' => 'Adressen die nooit meer een mailing krijgen, voor geen enkele lijst.',
    ],
    'create' => [
        'title' => 'Adres blokkeren',
        'description' => 'Het adres wordt van alle lijsten afgemeld en kan zich niet opnieuw aanmelden zolang het op de blokkeerlijst staat.',
    ],
    'fields' => [
        'email' => 'E-mailadres',
        'reason' => 'Reden',
        'source' => 'Bron',
        'note' => 'Notitie',
        'created_at' => 'Sinds',
    ],
    'help' => [
        'note' => 'Bijvoorbeeld waarom het adres geblokkeerd is.',
    ],
    'actions' => [
        'add' => 'Blokkeren',
        'delete_confirm' => 'Weet je zeker dat je dit adres van de blokkeerlijst wilt halen? Het kan zich daarna opnieuw aanmelden.',
    ],
    'flash' => [
        'saved' => 'Het adres staat op de blokkeerlijst.',
        'deleted' => 'Het adres is van de blokkeerlijst gehaald.',
    ],
];
