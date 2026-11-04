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
    'pay' => [
        'title' => 'Płatność za zamówienie :number',
        'description' => 'Zamówienie :number',
        'due' => 'Do zapłaty: :amount',
        'paid' => 'Zamówienie :number jest opłacone',
        'cancelled' => 'Zamówienie :number jest anulowane',
        'checking' => 'Sprawdzamy płatność. Zwykle trwa to do minuty.',
        'checking_long' => 'Bank jeszcze nie potwierdził płatności. Jeśli pieniądze zostały pobrane, zamówienie samo oznaczy się jako opłacone i wyślemy e-mail. Możesz zamknąć tę stronę.',
        'check_again' => 'Sprawdź ponownie',
        'held' => 'Płatność przyjęta. Kwota jest zablokowana na karcie, sklep ją pobierze.',
        'received' => 'Płatność otrzymana',
        'accepted' => 'Płatność przyjęta',
        'failed' => 'Płatność nie powiodła się',
        'unavailable' => 'Nie udało się otworzyć płatności. Spróbuj ponownie lub wybierz inną metodę.',
        'on_receipt' => 'Zamówienie jest opłacane przy odbiorze',
        'order_link' => 'Przejdź do zamówienia',
        'redirecting' => 'Przechodzimy do strony płatności…',
        'go' => 'Przejdź do płatności',
        'top_up' => 'Do dopłaty: :amount',
        'top_up_button' => 'Dopłać',
        'online_now' => 'Zapłać teraz online',
        'wait' => 'Jeśli już zapłaciłeś w innej karcie, poczekaj minutę, zanim zapłacisz ponownie.',
        'declined' => 'Płatność nie powiodła się: bank odrzucił kartę',
        'unfinished' => 'Płatność nie została ukończona',
        'pay_again' => 'Zapłać ponownie',
        'other_method' => 'Wybierz inną metodę',
    ],
];
