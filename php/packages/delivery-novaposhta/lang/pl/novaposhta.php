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
        'sender' => 'Nadawca',
        'contact' => 'Osoba kontaktowa',
        'sender_city' => 'Miejscowość nadania',
        'sender_point' => 'Oddział nadania',
        'locker_width' => 'Paczkomat: domyślna szerokość, cm',
        'locker_height' => 'Paczkomat: domyślna wysokość, cm',
        'locker_length' => 'Paczkomat: domyślna długość, cm',
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
    'waybill' => [
        'weight' => 'Waga, kg',
        'seats' => 'Paczki',
        'cargo_type' => 'Rodzaj ładunku',
        'description' => 'Opis ładunku',
        'date' => 'Data nadania',
        'width' => 'Szerokość, cm',
        'height' => 'Wysokość, cm',
        'length' => 'Długość, cm',
    ],
    'errors' => [
        'no_cod' => 'Płatność za pobraniem nie jest jeszcze obsługiwana przez tego przewoźnika.',
        'name_empty' => 'Wpisz imię i nazwisko odbiorcy.',
        'name_letters' => 'Przewoźnik przyjmuje imię tylko ukraińskimi literami.',
        'phone' => 'Przewoźnik przyjmuje ukraiński numer komórkowy: +380 i dziewięć cyfr.',
        'no_sender' => 'Najpierw wybierz w ustawieniach przewoźnika nadawcę, osobę kontaktową, miejscowość i oddział nadania.',
        'contact_gone' => 'Osoby kontaktowej z ustawień nie ma już na koncie przewoźnika: wybierz inną.',
        'no_town' => 'Przewoźnik nie zna miejscowości zamówienia: powiąż ją w kolejce miejscowości.',
        'no_point' => 'Zamówienie nie ma oddziału ani paczkomatu przewoźnika.',
        'no_street' => 'Zamówienie nie ma ulicy przewoźnika lub numeru domu.',
        'weight' => 'Waga musi być większa od zera.',
        'weight_too_much' => 'Waga jest większa, niż przyjmuje punkt.',
        'seats' => 'Liczba paczek to liczba całkowita od jednej.',
        'locker_seats' => 'Paczkomat przyjmuje jedną paczkę.',
        'cargo_type' => 'Wybierz rodzaj ładunku z listy.',
        'size' => 'Wymiary muszą być większe od zera.',
        'locker_size' => 'Paczkomat przyjmuje do 40 × 30 × 60 cm.',
        'date' => 'Data nadania to dziś lub dzień w ciągu trzech miesięcy.',
        'read_only' => 'Ta strona używa klucza przewoźnika tylko do odczytu: listy przewozowe są wyłączone.',
        'no_ref' => 'Identyfikator tego listu u przewoźnika nie jest zapisany: anuluj go na koncie przewoźnika.',
        'carrier_said' => 'Przewoźnik odmówił: :text',
    ],
    'remarks' => [
        'novaposhta_service_point' => 'Pominięto, punkty serwisowe przewoźnika: :count',
        'novaposhta_deny_to_select' => 'Pominięto, niedostępne do wyboru: :count',
        'novaposhta_invalid_point' => 'Pominięto, z niepełnymi danymi: :count',
    ],
];
