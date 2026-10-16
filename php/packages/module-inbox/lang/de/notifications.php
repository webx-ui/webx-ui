<?php

declare(strict_types=1);

// The event `inbox.submission` of webx-ui/module-notifications: its name and the values its templates get.
return [
    'event' => 'Eine Einsendung eines Formulars',
    'vars' => [
        'form' => 'Der Name des Formulars',
        'form_slug' => 'Der Slug des Formulars',
        'submission_id' => 'Die Nummer der Einsendung',
        'submission_url' => 'Die Einsendung im Panel',
        'created_at' => 'Wann sie einging',
        'fields' => 'Alle Antworten, je eine Zeile: „Bezeichnung: Wert“',
        'answers' => 'Die Antworten als Liste',
        'answer_name' => 'Der Maschinenname des Feldes',
        'answer_label' => 'Die Bezeichnung des Feldes',
        'answer_value' => 'Die Antwort',
        'field' => 'Eine Antwort, nach dem Maschinennamen ihres Feldes',
        'files' => 'Die Namen der angehängten Dateien',
        'page' => 'Die Seite, von der das Formular gesendet wurde',
        'locale' => 'Die Sprache der Einsendung',
        'utm' => 'UTM-Parameter',
        'reply_to' => 'Die E-Mail des Besuchers aus dem E-Mail-Feld des Formulars',
    ],
];
