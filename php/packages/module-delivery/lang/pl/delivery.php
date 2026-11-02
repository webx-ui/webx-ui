<?php

declare(strict_types=1);

return [
    'title' => 'Dostawa',
    'cache' => 'Metody dostawy i przewoźnicy',
    'cache_points' => 'Punkty odbioru przewoźników',
    'carriers' => [
        'store' => 'Sklep',
    ],
    'point_kinds' => [
        'branch' => 'Oddział',
        'locker' => 'Paczkomat',
        'store' => 'Sklep',
        'pickup' => 'Punkt odbioru',
    ],
    'locality_kinds' => [
        'city' => 'Miasto',
        'town' => 'Miasto',
        'township' => 'Osiedle typu miejskiego',
        'village' => 'Wieś',
        'settlement' => 'Osada',
        'other' => 'Miejscowość',
    ],
    'step' => [
        'delivery' => 'Dostawa',
        'locality' => 'Miasto lub miejscowość',
        'locality_placeholder' => 'Zacznij wpisywać nazwę',
        'where' => 'Dokąd dostarczyć zamówienie?',
        'choose_locality' => 'Wybierz miasto',
        'change' => 'Zmień',
        'popular' => 'Popularne miasta',
        'show_methods' => 'Pokaż metody dostawy',
        'no_methods' => 'Brak metod dostawy w tym miejscu',
        'free' => 'Za darmo',
        'carrier_rates' => 'Według taryfy przewoźnika',
        'choose_point' => 'Wybierz oddział',
        'pick_up_here' => 'Odbiorę tutaj',
        'open_until' => 'Dziś czynne do :time',
        'closed_today' => 'Dziś nieczynne',
        'street' => 'Ulica',
        'house' => 'Numer domu',
        'apartment' => 'Mieszkanie',
        'note' => 'Uwagi dla kuriera',
        'cost' => 'Koszt dostawy',
        'points' => 'Punktów: :count',
    ],
    // The line of the cart's totals, and the benefit the shop's cart rules may grant (§9.4).
    'totals' => [
        'delivery' => 'Dostawa',
    ],
    'benefits' => [
        'paid_by_store' => 'Sklep płaci za dostawę',
    ],
    'hints' => [
        'no_streets' => 'Kurier tu nie dostarcza, wybierz inną metodę',
    ],
    // A street of a carrier's directory as shown: its name with the type word where the language puts it.
    'street_kinds' => [
        'street' => 'ul. :name',
        'avenue' => 'al. :name',
        'lane' => 'zaułek :name',
        'square' => 'pl. :name',
        'other' => ':name',
    ],
    'errors' => [
        'choose_point' => 'Wybierz punkt odbioru.',
        'point_gone' => 'Punkt odbioru nie jest już dostępny, wybierz inny.',
        'choose_street' => 'Wybierz ulicę z listy.',
        'street_required' => 'Podaj ulicę.',
        'house_required' => 'Podaj numer domu.',
        'too_long' => 'Najwyżej :max znaków.',
        'no_quote' => 'Nie udało się obliczyć dostawy, wybierz inną metodę.',
    ],
    'sync' => [
        'running' => 'Już trwa, strona :page',
        'errors' => [
            'no_answer' => 'Przewoźnik nie odpowiedział',
            'key_rejected' => 'Przewoźnik odrzucił klucz API',
            'bad_response' => 'Odpowiedź przewoźnika miała nieoczekiwany format',
            'failed' => 'Synchronizacja nie powiodła się po stronie witryny — szczegóły w logu',
            'interrupted' => 'Synchronizacja została przerwana',
        ],
    ],
    'schedule' => [
        'closed' => 'Zamknięte',
        'around_the_clock' => 'Czynne całą dobę',
        'until' => 'Do :time',
        'from' => 'Otwarcie o :time',
    ],
];
