<?php

declare(strict_types=1);

return [
    'public' => [
        'accepted' => [
            'name' => 'Przyjęte',
            'description' => 'Mamy Twoje zamówienie i odezwiemy się.',
        ],
        'in_progress' => [
            'name' => 'W realizacji',
            'description' => 'Kompletujemy Twoje zamówienie.',
        ],
        'shipped' => [
            'name' => 'Wysłane',
            'description' => 'Zamówienie jest w drodze.',
        ],
        'completed' => [
            'name' => 'Zrealizowane',
            'description' => 'Zamówienie jest u Ciebie. Dziękujemy!',
        ],
        'cancelled' => [
            'name' => 'Anulowane',
            'description' => 'Zamówienie zostało anulowane.',
        ],
    ],
    'internal' => [
        'new' => 'Nowe',
        'awaiting_payment' => 'Oczekuje na płatność',
        'taken' => 'Przyjęte do realizacji',
        'awaiting_call' => 'Czeka na telefon',
        'packing' => 'Kompletowane',
        'handed_to_delivery' => 'Przekazane do dostawy',
        'completed' => 'Zrealizowane',
        'cancelled' => 'Anulowane',
    ],
];
