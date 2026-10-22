<?php

declare(strict_types=1);

return [
    'title' => 'Nazwa',
    'code' => 'Kod',
    'code-help' => 'Litery łacińskie, cyfry i myślniki: status w adresie filtra (stock_in-stock) i w szablonach. Puste — tworzony z nazwy.',
    'color' => 'Kolor',
    'color-help' => 'Strona maluje wiersz statusu w tym kolorze; panel pokazuje znacznik w tym samym kolorze.',
    'tones' => [
        'neutral' => 'Neutralny',
        'primary' => 'Główny',
        'success' => 'Sukces',
        'warning' => 'Ostrzeżenie',
        'danger' => 'Zagrożenie',
        'info' => 'Informacja',
    ],
    'purchasable' => 'Można kupić',
    'purchasable-help' => 'Wyłączone: produktu w tym statusie nie można kupić, a jego nazwa jest tym, co strona pokazuje zamiast przycisku zakupu.',
    'default' => 'Domyślny',
    'default-help' => 'Status każdego produktu, któremu nikt nie ustawił statusu, oraz nowego produktu. Aby go zmienić, ustaw inny status jako domyślny.',
    'filterable' => 'W filtrze',
    'filterable-help' => 'Wyłączone: status nie jest opcją filtra katalogu.',
    'new' => 'Nowy status',
    'empty' => 'Nie ma jeszcze statusów dostępności.',
    'empty-help' => 'Status mówi, czy produkt jest na stanie i czy można go kupić.',
    'order' => 'Kolejność tutaj to kolejność w filtrze',
    'hidden' => 'Nie w filtrze',
    'count' => 'Produkty: :count',
    'show-items' => 'Pokaż jego produkty',
    'delete-blocked' => 'Dopóki są w nim produkty, nie można go usunąć — najpierw przenieś je do innego statusu.',
    'delete-text' => 'Trafi do kosza i zniknie z filtra.',
    'deleted' => 'Status jest w koszu.',
    'saved' => 'Status zapisany.',
];
