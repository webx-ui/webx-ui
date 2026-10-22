<?php

declare(strict_types=1);

return [
    'type' => 'Właściwość to jedno z: :types.',
    'type-fixed' => 'Typ właściwości nie zmienia się po zapisaniu: utwórz nową właściwość i przenieś wartości.',
    'code' => 'Litery łacińskie, cyfry i pojedyncze myślniki, najwyżej :max znaków.',
    'code-taken' => 'Ten kod jest w tym języku zajęty przez: :holder.',
    'slug' => 'Litery łacińskie, cyfry i pojedyncze myślniki.',
    'slug-taken' => 'Inna wartość tej właściwości ma ten slug w tym języku.',
    'color' => 'Kolor to #rrggbb.',
    'group' => 'Nie ma takiej grupy.',
    'group-in-use' => 'Grupa zawiera właściwości. Właściwości: :count',
    'unknown-property' => 'Nie ma takiej właściwości.',
    'inherited' => '«:property» jest już w zestawie «:category» wyżej; kategoria ją dziedziczy.',
    'outside-set' => 'Tej właściwości nie ma w zestawie kategorii głównej produktu.',
    'number' => 'Oczekiwana jest liczba.',
    'text' => 'Oczekiwany jest tekst lub mapa języków.',
    'one-value' => 'Ta właściwość przyjmuje jedną wartość.',
    'unknown-value' => 'Nie ma takiej wartości tej właściwości.',
    'unknown-slug' => '«:property» nie ma wartości «:slug». Najpierw utwórz ją przez catalog_property_values_create albo podaj id.',
    'leaves-only' => 'Tutaj można wybrać tylko wartość, pod którą nic nie ma.',
    'value-in-use' => 'Produkty mają tę wartość — zamiast tego scal ją z inną. Produkty: :count',
    'values-select' => 'Wartości ma tylko słownik.',
    'not-tree' => 'Wartości tej właściwości nie tworzą drzewa.',
    'move-into-itself' => 'Wartości nie można umieścić pod nią samą.',
    'merge-other' => 'Wartość scala się z inną wartością tej samej właściwości.',
    'merge-descendant' => 'Wartości nie można scalić z wartością pod nią.',
    'intervals-number' => 'Przedziały ma tylko liczba.',
    'interval-ends' => 'Górna granica musi być wyższa od dolnej.',
];
