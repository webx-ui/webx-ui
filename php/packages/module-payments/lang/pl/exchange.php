<?php

declare(strict_types=1);

// The journal of the exchange with gateways (spec §20): directions, kinds, signatures, outcomes.
return [
    'title' => 'Wymiana z bramką',
    'directions' => [
        'out' => 'Do bramki',
        'in' => 'Z bramki',
        'browser' => 'Przez przeglądarkę kupującego',
        'internal' => 'Decyzja strony',
    ],
    'kinds' => [
        'start' => 'Płatność otwarta',
        'status' => 'Zapytanie o status',
        'cancel' => 'Anulowanie',
        'complete' => 'Zatwierdzenie zakończone',
        'capture' => 'Żądanie pobrania',
        'void' => 'Żądanie zwolnienia',
        'connection' => 'Test połączenia',
        'key' => 'Klucz podpisu',
        'other' => 'Inne zapytanie',
        'notify' => 'Powiadomienie',
        'handoff' => 'Przekazano do bramki',
        'return' => 'Powrót z bramki',
        'expire' => 'Wygasło',
        'manual' => 'Oznaczono ręcznie',
        'captured' => 'Pobrano',
        'released' => 'Blokada zwolniona',
        'capture_refused' => 'Pobranie odrzucone',
        'release_refused' => 'Zwolnienie odrzucone',
    ],
    'signature' => [
        'valid' => 'Podpis poprawny',
        'invalid' => 'Podpis niepoprawny',
        'untrusted' => 'Bez podpisu — status pobrany z bramki',
        'none' => 'Nie sprawdzano',
    ],
    'outcomes' => [
        'applied' => 'Zastosowano',
        'same' => 'Bez zmian',
        'ignored' => 'Pominięto',
        'duplicate' => 'Powtórzenie',
        'rejected' => 'Odrzucono',
        'unmatched' => 'Brak takiej płatności',
        'error' => 'Błąd',
    ],
    'truncated' => 'Przycięto do limitu',
    'download' => 'Pobierz',
    'size' => 'Rozmiar',
    'masked' => 'Sekrety ukryte',
];
