<?php

declare(strict_types=1);

return [
    'empty' => 'Adres, pytanie i odpowiedź są wymagane.',
    'question-long' => 'Pytanie ma ponad 1000 znaków.',
    'foreign-host' => 'Adres jest na innej stronie.',
    'duplicate' => 'To pytanie jest już na stronie.',
    'redirected' => ':from przekierowuje; zamiast niego użyto celu :to.',
    'unreadable' => 'Nie udało się odczytać pliku jako CSV ani XLSX.',
    'has-faq' => 'Ta reguła ma FAQ, a zachować je może tylko reguła dla jednego dokładnego adresu. Najpierw usuń pytania.',
    'question-missing' => 'Odpowiedź nie ma pytania.',
    'answer-missing' => 'Pytanie nie ma odpowiedzi.',

    // The panel's view of it (§18.5).
    'tab' => 'FAQ',
    'meta' => 'Metatagi',
    'help' => 'Pytania tej strony. Trafiają do jej znaczników FAQPage i na stronę tam, gdzie szablon wyświetla FAQ.',
    'exact-only' => 'FAQ ma tylko reguła dla jednego dokładnego adresu.',
    'question' => 'Pytanie',
    'answer' => 'Odpowiedź',
    'add' => 'Dodaj pytanie',
    'remove' => 'Usuń pytanie',
    'drag' => 'Przenieś',
    'none' => 'Nie ma jeszcze pytań.',
    'column' => 'FAQ',
    'with-faq' => 'Tylko z FAQ',
    'import' => 'Importuj FAQ',
    'export-csv' => 'Eksportuj FAQ do CSV',
    'export-xlsx' => 'Eksportuj FAQ do XLSX',
    'import-title' => 'Import FAQ',
    'import-help' => 'Plik CSV lub XLSX z kolumnami adres, pytanie i odpowiedź (HTML lub tekst) w języku adresu. Adres bez dokładnej reguły dostanie ją z pustymi metatagami.',
    'mode-replace' => 'Zastąp FAQ adresów z pliku',
    'mode-append' => 'Dopisz do istniejących pytań',
    'imported' => 'Zaimportowano. Adresy: :count',
    'result-addresses' => 'Adresy',
    'result-questions' => 'Pytania',
    'result-created' => 'Nowe reguły',
    'result-replaced' => 'Zastąpione',
    'result-appended' => 'Uzupełnione',
    'result-errors' => 'Błędy',
];
