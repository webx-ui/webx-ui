<?php

declare(strict_types=1);

return [
    'orders' => 'Zamówienia',
    'description' => 'Zamówienia złożone na stronie: co kupiono, kto i na jakim etapie jest każde z nich.',
    'empty' => 'Na razie brak zamówień.',
    'empty-help' => 'Zamówienie pojawi się tutaj, gdy tylko zostanie złożone na stronie.',
    'order' => 'Zamówienie',
    'statuses-cache' => 'Statusy zamówień',
    'order-field' => [
        'title' => 'Pole zamówienia',
        'cache' => 'Pola zamówienia',
        'key' => 'Klucz',
        'type' => 'Typ',
        'place' => 'Miejsce',
        'label' => 'Nazwa',
        'hint' => 'Podpowiedź',
        'options' => 'Opcje',
        'required' => 'Wymagane',
        'active' => 'Włączone',
        'parent_id' => 'Pokaż po zaznaczeniu',
    ],
    'fields' => [
        'customer_first_name' => 'Imię',
        'customer_last_name' => 'Nazwisko',
        'customer_phone' => 'Telefon',
        'customer_email' => 'E-mail',
        'recipient' => 'Odbiorca',
        'comment' => 'Komentarz kupującego',
        'manager_note' => 'Notatka menedżera',
        'user_id' => 'Odwiedzający',
        'fields' => 'Dodatkowe pola',
    ],
    'milestones' => [
        'placed' => 'Złożone',
        'awaiting_payment' => 'Oczekuje na płatność',
        'paid' => 'Opłacone',
        'received' => 'Odebrane',
        'cancelled' => 'Anulowane',
        'refunded' => 'Zwrócone',
    ],
    'colors' => [
        'neutral' => 'Neutralny',
        'info' => 'Informacja',
        'success' => 'Sukces',
        'warning' => 'Uwaga',
        'danger' => 'Zagrożenie',
        'accent' => 'Akcent',
    ],
];
