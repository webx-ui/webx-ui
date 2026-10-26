<?php

declare(strict_types=1);

return [
    'schema' => [
        'invalid' => 'Schemat nie jest obiektem JSON.',
        'version' => 'Nieznana wersja schematu: moduł czyta wersję 1.',
    ],
    'currency' => [
        'invalid' => 'Waluta to trzyliterowy kod ISO 4217 wielkimi literami, np. UAH lub EUR.',
    ],
    'pricing' => [
        'round' => 'Krok zaokrąglenia to liczba większa od 0.',
    ],
    'discount' => [
        'percent' => 'Rabat to procent większy od 0 i mniejszy od 100.',
        'until' => 'Ostatni dzień rabatu to data RRRR-MM-DD.',
    ],
    'steps' => [
        'empty' => 'Konfigurator potrzebuje co najmniej jednego kroku.',
    ],
    'key' => [
        'invalid' => 'Klucz „:key” nie składa się z a-z, 0-9 i _ z literą na początku.',
        'duplicate' => 'Klucz „:key” jest zajęty: kroki i pola wymiarów dzielą klucze.',
    ],
    'type' => [
        'unknown' => 'Nieznany typ kroku: cards, swatches, buttons lub dimensions.',
    ],
    'options' => [
        'empty' => 'Krok wyboru potrzebuje co najmniej jednej opcji.',
    ],
    'fields' => [
        'empty' => 'Krok wymiarów potrzebuje co najmniej jednego pola.',
    ],
    'value' => [
        'invalid' => 'Wartość „:value” nie jest slugiem: a-z, 0-9, - i _.',
        'duplicate' => 'Wartość „:value” występuje w tym kroku dwa razy.',
    ],
    'label' => [
        'missing' => 'Potrzebna jest nazwa w języku domyślnym (:locale).',
    ],
    'text' => [
        'invalid' => 'Tekst to ciąg znaków lub zestaw języków.',
    ],
    'image' => [
        'missing' => 'Wybierz obraz z biblioteki mediów.',
        'unknown' => 'W bibliotece mediów nie ma pliku „:key”.',
    ],
    'product' => [
        'invalid' => 'Produkt wskazuje się kodem (SKU), do 64 znaków.',
        'unknown' => 'W katalogu nie ma produktu o kodzie «:sku».',
    ],
    'swatch' => [
        'invalid' => 'Próbka to #rgb, #rrggbb lub gradient z kolorów hex.',
    ],
    'price' => [
        'invalid' => 'Cena to liczba.',
        'per_m2' => 'Cena za m² wymaga powierzchni: daj opcjom jednego kroku wzór powierzchni.',
    ],
    'area' => [
        'main_count' => 'Wzór powierzchni mogą mieć opcje tylko jednego kroku.',
        'missing' => 'Każda opcja kroku głównego potrzebuje wzoru powierzchni.',
        'syntax' => 'Błąd we wzorze przy znaku :position: :reason.',
        'unknown_key' => 'Wzór używa „:key”, a takiego pola wymiarów nie ma.',
    ],
    'range' => [
        'invalid' => 'Zły zakres: min większe od max albo krok nie większy od 0.',
    ],
    'limits' => [
        'unknown_field' => 'limits wskazuje „:key”, a takiego pola wymiarów nie ma.',
    ],
    'defaults' => [
        'unknown_field' => 'defaults wskazuje „:key”, a takiego pola wymiarów nie ma.',
    ],
    'condition' => [
        'invalid' => 'Warunek to krok i wartości, przy których jest spełniony.',
        'unknown_step' => 'Nie ma kroku „:step”.',
        'forward' => 'Krok „:step” nie stoi powyżej tego: warunek patrzy tylko w górę.',
        'not_choice' => 'Krok „:step” to krok wymiarów: warunek wskazuje krok z opcjami.',
        'unknown_value' => 'Krok „:step” nie ma opcji „:value”.',
    ],
    'initial' => [
        'unknown' => 'Wybór początkowy „:key” nie jest krokiem, polem ani jego wartością.',
    ],
    'previews' => [
        'condition' => 'Reguła obrazu wskazuje krok lub wartość, której nie ma.',
    ],
    'values' => [
        'range' => 'Wymiar „:key” to od :min do :max.',
        'number' => 'Wymiar „:key” to liczba.',
    ],
];
