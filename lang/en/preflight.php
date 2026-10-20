<?php

declare(strict_types=1);

return [
    'items' => [
        'spf' => 'SPF',
        'dkim' => 'DKIM',
        'dmarc' => 'DMARC',
        'alignment' => 'Alignment',
        'blocklists' => 'Blocklists',
        'spam_score' => 'Spam score',
        'links' => 'Links',
        'text' => 'Text',
    ],
    'messages' => [
        'spf' => [
            'missing' => 'No SPF record found for :domain.',
            'incomplete' => 'The SPF record lacks: :missing.',
            'ok' => 'SPF: :record',
        ],
        'dkim' => [
            'unknown' => 'No DKIM selector configured for this transport; check DKIM yourself.',
            'missing' => 'The DKIM selector :selector was not found.',
            'ok' => 'DKIM found for :selectors.',
        ],
        'dmarc' => [
            'missing' => 'No DMARC record found for :domain.',
            'ok' => 'DMARC: :record',
        ],
        'alignment' => [
            'provider' => 'Check at the provider that Return-Path and DKIM align with the From domain.',
            'from_domain' => 'Bounces go to the From domain itself.',
            'ok' => 'The bounce domain :domain aligns with the From domain.',
            'mismatch' => 'The bounce domain :bounce does not align with :from.',
        ],
        'blocklists' => [
            'unknown' => 'No sending IP configured; the blocklists were not checked.',
            'listed' => 'On a blocklist: :listed.',
            'refused' => 'These lists did not answer: :zones.',
            'ok' => 'Not found on the blocklists (:ips).',
        ],
        'spam_score' => [
            'unavailable' => 'No spam filter available; the spam score was not checked.',
            'high' => 'Spam score :score is too high.',
            'ok' => 'Spam score :score.',
        ],
        'links' => [
            'broken' => 'These links do not work or are not https: :urls',
            'unverified' => 'These links did not answer with a plain 200 or could not be checked in time. Please check them yourself: :urls',
            'ok' => ':count links checked.',
        ],
        'text' => [
            'missing' => 'The mail has too little text or no plain-text part.',
            'ok' => 'Text and plain-text part present.',
        ],
    ],
];
