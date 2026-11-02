<?php

declare(strict_types=1);

return [
    'carrier' => [
        'title' => 'Nova Poshta',
    ],
    'types' => [
        'branch' => [
            'title' => 'Oddział Nova Poshta',
            'description' => 'Odbiór w oddziale przewoźnika; za dostawę płacisz przewoźnikowi przy odbiorze.',
        ],
        'locker' => [
            'title' => 'Paczkomat Nova Poshta',
            'description' => 'Odbiór z paczkomatu przewoźnika; za dostawę płacisz przewoźnikowi przy odbiorze.',
        ],
        'courier' => [
            'title' => 'Kurier Nova Poshta',
            'description' => 'Przewoźnik dostarczy paczkę pod drzwi; za dostawę płacisz przewoźnikowi przy odbiorze.',
        ],
    ],
    'settings' => [
        'payment_method' => 'Płatność za dostawę',
        'payment_methods' => [
            'Cash' => 'Gotówka',
            'NonCash' => 'Bezgotówkowo (na podstawie umowy z przewoźnikiem)',
        ],
        'cargo_type' => 'Domyślny rodzaj ładunku',
        'cargo_types' => [
            'Parcel' => 'Paczka',
            'Cargo' => 'Ładunek',
            'Documents' => 'Dokumenty',
        ],
        'weight' => 'Domyślna waga, kg',
        'description' => 'Domyślny opis ładunku',
        'description_default' => 'Towary',
    ],
    'credentials' => [
        'api_key' => 'Klucz API',
    ],
    'connection' => [
        'ok' => 'Nadawców na koncie: :count',
        'key_refused' => 'Przewoźnik nie przyjął klucza API.',
        'no_answer' => 'Przewoźnik nie odpowiedział.',
        'no_sender' => 'Na koncie przewoźnika nie ma nadawcy.',
        'no_key' => 'Najpierw wpisz klucz API.',
    ],
];
