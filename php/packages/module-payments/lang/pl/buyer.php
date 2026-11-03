<?php

declare(strict_types=1);

// What the buyer reads (spec §10, §12, §17): the payment step, the bank details, the order's line.
return [
    'step' => [
        'methods' => 'Metoda płatności',
        'save' => 'Zapisz',
        'delivery_first' => 'Najpierw wybierz dostawę — od niej zależą metody płatności',
        'method_dropped' => 'Wybrana metoda płatności jest niedostępna dla tej dostawy. Wybierz inną.',
    ],
    'errors' => [
        'choose_method' => 'Wybierz metodę płatności',
        'method_gone' => 'Metoda płatności nie jest już dostępna, wybierz inną',
    ],
    'instructions' => [
        'title' => 'Dane do przelewu',
        'recipient' => 'Odbiorca',
        'account' => 'Rachunek',
        'purpose' => 'Tytuł przelewu',
        'note' => 'Termin',
    ],
    'status' => [
        'paid' => 'Opłacone',
        'awaiting' => 'Oczekuje na płatność',
        'pay' => 'Zapłać',
    ],
];
