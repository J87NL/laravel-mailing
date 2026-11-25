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
        'send_confirmations' => 'Ik weet dat elk adres een bevestigingsmail krijgt',
        'imported' => 'Geïmporteerd',
        'skipped' => 'Overgeslagen',
        'confirmations_sent' => 'Bevestigingsmails',
        'created_at' => 'Moment',
    ],
    'help' => [
        'file' => 'Kolommen email (of e-mailadres) en name (of naam), gescheiden door komma of puntkomma. Een kopregel is optioneel.',
        'consent_source' => 'Bijvoorbeeld "papieren intekenlijst rommelmarkt mei 2026".',
        'consent_note' => 'Vul dit alleen in als de toestemming aantoonbaar elders is vastgelegd. Dan worden de adressen direct aangemeld en krijgen ze geen mail. Laat je het leeg, dan krijgt elk adres een bevestigingsmail en komt het pas op de lijst na een klik op de link in die mail.',
    ],
    'actions' => [
        'import' => 'Importeren',
        'new' => 'Nieuwe import',
    ],
    'errors' => [
        'invalid_email' => 'Regel :line: geen geldig e-mailadres.',
        'too_many_rows' => 'Het bestand heeft meer dan :max regels; alleen de eerste :max zijn verwerkt.',
        'row_failed' => 'Regel :line kon niet worden verwerkt.',
        'mail_failed' => 'Regel :line is opgeslagen, maar de bevestigingsmail kon niet worden verstuurd. Importeer het bestand opnieuw om die mail alsnog te sturen.',
        'confirmations_not_acknowledged' => 'Zonder vastgelegde toestemming krijgt elk adres een bevestigingsmail. Vink aan dat je dat weet, of vul in waar de toestemming is vastgelegd.',
        'no_lists' => 'Maak eerst een mailinglijst aan.',
    ],
    'flash' => [
        'imported' => ':imported geïmporteerd, :skipped overgeslagen, :confirmations bevestigingsmails verstuurd.',
    ],
];
