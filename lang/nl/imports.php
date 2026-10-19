<?php

declare(strict_types=1);

return [
    'index' => [
        'title' => 'Imports',
        'description' => 'Adressen uit een CSV-bestand op een lijst zetten, altijd met een bron van toestemming.',
    ],
    'create' => [
        'title' => 'Adressen importeren',
        'description' => 'Een CSV met een kolom email en optioneel name. Geïmporteerde adressen krijgen één bevestigingsmail, tenzij je vastlegt waar de eerdere toestemming is gedocumenteerd.',
    ],
    'fields' => [
        'list' => 'Lijst',
        'file' => 'CSV-bestand',
        'consent_source' => 'Bron van toestemming',
        'consent_note' => 'Waar is de toestemming vastgelegd?',
        'imported' => 'Geïmporteerd',
        'skipped' => 'Overgeslagen',
        'confirmations_sent' => 'Bevestigingsmails',
        'created_at' => 'Moment',
    ],
    'help' => [
        'file' => 'Kolommen email (of e-mailadres) en name (of naam), gescheiden door komma of puntkomma. Een kopregel is optioneel.',
        'consent_source' => 'Bijvoorbeeld "papieren intekenlijst rommelmarkt mei 2026".',
        'consent_note' => 'Alleen invullen als de toestemming aantoonbaar elders is vastgelegd. Dan worden de adressen direct aangemeld zonder bevestigingsmail.',
    ],
    'actions' => [
        'import' => 'Importeren',
        'new' => 'Nieuwe import',
    ],
    'errors' => [
        'invalid_email' => 'Regel :line: geen geldig e-mailadres.',
        'too_many_rows' => 'Het bestand heeft meer dan :max regels; alleen de eerste :max zijn verwerkt.',
        'no_lists' => 'Maak eerst een mailinglijst aan.',
    ],
    'flash' => [
        'imported' => ':imported geïmporteerd, :skipped overgeslagen, :confirmations bevestigingsmails verstuurd.',
    ],
];
