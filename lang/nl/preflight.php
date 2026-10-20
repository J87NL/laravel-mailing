<?php

declare(strict_types=1);

return [
    'items' => [
        'spf' => 'SPF',
        'dkim' => 'DKIM',
        'dmarc' => 'DMARC',
        'alignment' => 'Uitlijning',
        'blocklists' => 'Blokkeerlijsten',
        'spam_score' => 'Spamscore',
        'links' => 'Links',
        'text' => 'Tekst',
    ],
    'messages' => [
        'spf' => [
            'missing' => 'Geen SPF-record gevonden voor :domain.',
            'incomplete' => 'Het SPF-record mist: :missing.',
            'ok' => 'SPF: :record',
        ],
        'dkim' => [
            'unknown' => 'Geen DKIM-selector ingesteld voor deze verzendroute; controleer DKIM zelf.',
            'missing' => 'De DKIM-selector :selector is niet gevonden.',
            'ok' => 'DKIM gevonden voor :selectors.',
        ],
        'dmarc' => [
            'missing' => 'Geen DMARC-record gevonden voor :domain.',
            'ok' => 'DMARC: :record',
        ],
        'alignment' => [
            'provider' => 'Controleer bij de provider of Return-Path en DKIM bij het afzenderdomein horen.',
            'from_domain' => 'Bounces gaan naar het afzenderdomein zelf.',
            'ok' => 'Het bouncedomein :domain hoort bij het afzenderdomein.',
            'mismatch' => 'Het bouncedomein :bounce hoort niet bij :from.',
        ],
        'blocklists' => [
            'unknown' => 'Geen verzend-IP ingesteld; de blokkeerlijsten zijn niet gecontroleerd.',
            'listed' => 'Op een blokkeerlijst: :listed.',
            'refused' => 'Deze lijsten gaven geen antwoord: :zones.',
            'ok' => 'Niet gevonden op de blokkeerlijsten (:ips).',
        ],
        'spam_score' => [
            'unavailable' => 'Geen spamfilter beschikbaar; de spamscore is niet gecontroleerd.',
            'high' => 'Spamscore :score is te hoog.',
            'ok' => 'Spamscore :score.',
        ],
        'links' => [
            'broken' => 'Deze links werken niet of zijn geen https: :urls',
            'unverified' => 'Deze links gaven geen nette 200-reactie of zijn niet op tijd gecontroleerd. Kijk ze even zelf na: :urls',
            'ok' => ':count links gecontroleerd.',
        ],
        'text' => [
            'missing' => 'De mail heeft te weinig tekst of geen tekstversie.',
            'ok' => 'Tekst en tekstversie aanwezig.',
        ],
    ],
];
