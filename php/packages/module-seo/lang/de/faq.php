<?php

declare(strict_types=1);

return [
    'empty' => 'Adresse, Frage und Antwort sind erforderlich.',
    'question-long' => 'Die Frage ist länger als 1000 Zeichen.',
    'foreign-host' => 'Die Adresse liegt auf einer anderen Website.',
    'duplicate' => 'Diese Frage steht schon auf der Seite.',
    'redirected' => ':from leitet weiter; stattdessen wird das Ziel :to verwendet.',
    'unreadable' => 'Die Datei lässt sich nicht als CSV oder XLSX lesen.',
    'has-faq' => 'Diese Regel hat ein FAQ, und nur eine Regel für eine exakte Adresse kann eines behalten. Entfernen Sie zuerst die Fragen.',
    'question-missing' => 'Die Antwort hat keine Frage.',
    'answer-missing' => 'Die Frage hat keine Antwort.',

    // The panel's view of it (§18.5).
    'tab' => 'FAQ',
    'meta' => 'Meta-Tags',
    'help' => 'Die Fragen dieser Seite. Sie gehen in ihr FAQPage-Markup und auf die Seite, wo die Vorlage das FAQ ausgibt.',
    'exact-only' => 'Nur eine Regel für eine exakte Adresse hat ein FAQ.',
    'question' => 'Frage',
    'answer' => 'Antwort',
    'add' => 'Frage hinzufügen',
    'remove' => 'Frage entfernen',
    'drag' => 'Verschieben',
    'none' => 'Noch keine Fragen.',
    'column' => 'FAQ',
    'with-faq' => 'Nur mit FAQ',
    'import' => 'FAQ importieren',
    'export-csv' => 'FAQ als CSV exportieren',
    'export-xlsx' => 'FAQ als XLSX exportieren',
    'import-title' => 'FAQ importieren',
    'import-help' => 'Eine CSV- oder XLSX-Datei mit den Spalten Adresse, Frage und Antwort (HTML oder Text) in der Sprache der Adresse. Eine Adresse ohne exakte Regel bekommt eine mit leeren Meta-Tags.',
    'mode-replace' => 'Das FAQ der Adressen aus der Datei ersetzen',
    'mode-append' => 'Zu den vorhandenen Fragen hinzufügen',
    'imported' => 'Importiert. Adressen: :count',
    'result-addresses' => 'Adressen',
    'result-questions' => 'Fragen',
    'result-created' => 'Neue Regeln',
    'result-replaced' => 'Ersetzt',
    'result-appended' => 'Ergänzt',
    'result-errors' => 'Fehler',
];
