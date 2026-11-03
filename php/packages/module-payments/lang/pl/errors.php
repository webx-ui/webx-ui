<?php

declare(strict_types=1);

// Refusals of the service, the API and the agent's tools (spec §17).
return [
    'mark' => [
        'paid' => 'Zamówienie jest już opłacone',
        'cancelled' => 'Zamówienie jest anulowane',
    ],
    'begin' => [
        'cancelled' => 'Zamówienie jest anulowane',
        'nothing_due' => 'Za zamówienie nie ma nic do zapłaty',
        'not_online' => 'Metoda nie jest płatnością online',
        'paid' => 'Zamówienie jest już opłacone',
        'held' => 'Środki za zamówienie są zablokowane: sklep je pobierze',
    ],
    'hold' => [
        'not_held' => 'W tej płatności nie ma zablokowanych środków',
        'no_hold' => 'Ta metoda płatności nie może blokować środków',
        'amount' => 'Kwota musi być większa od zera',
        'too_much' => 'Więcej niż zablokowano',
        'partial' => 'Ta bramka pobiera tylko całą zablokowaną kwotę',
        'busy' => 'Płatność jest właśnie aktualizowana, spróbuj ponownie za minutę',
    ],
    'problem' => [
        'none' => 'Płatność nie ma problemu',
        'hold' => 'Blokadę rozwiązuje jej pobranie lub zwolnienie',
    ],
];
