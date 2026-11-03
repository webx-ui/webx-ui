<?php

declare(strict_types=1);

// The built-in payment methods (spec §12, §17): their default titles, notes and settings.
return [
    'on_receipt' => [
        'title' => 'Płatność przy odbiorze',
        'pickup' => 'Płatność w kasie',
        'cod_note' => 'Firma kurierska pobiera własną opłatę za pobranie',
        'settings' => [
            'title_pickup' => 'Nazwa przy odbiorze w sklepie',
            'cod_note' => 'Uwaga o opłacie za pobranie',
        ],
    ],
    'bank_transfer' => [
        'title' => 'Przelew bankowy',
        'purpose' => 'Płatność za zamówienie {number}',
        'settings' => [
            'recipient' => 'Odbiorca',
            'account' => 'Rachunek i bank',
            'purpose' => 'Tytuł przelewu ({number} — numer zamówienia)',
            'note' => 'Termin płatności',
        ],
    ],
];
