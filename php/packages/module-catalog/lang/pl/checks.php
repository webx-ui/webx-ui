<?php

declare(strict_types=1);

return [
    'catalog' => [
        'category_description' => [
            'title' => 'Kategorie bez opisu',
            'found' => 'Opublikowane kategorie nie mają opisu.',
            'why' => 'Strona kategorii, na której jest tylko lista produktów, mówi wyszukiwarce niewiele i łatwo uchodzi za stronę o ubogiej treści.',
            'fix' => 'Napisz kilka zdań o tym, co jest w kategorii i jak w niej wybierać.',
        ],
        'product_image' => [
            'title' => 'Produkty bez obrazka',
            'found' => 'Opublikowane produkty nie mają zdjęcia.',
            'why' => 'Produkt bez obrazka sprzedaje się gorzej, na liście wygląda na niedokończony i nie ma czego pokazać przy udostępnianiu.',
            'fix' => 'Dodaj do każdego produktu co najmniej jedno zdjęcie albo zdejmij ze strony te, które nie są gotowe.',
        ],
        'product_price' => [
            'title' => 'Produkty bez ceny',
            'found' => 'Opublikowane produkty nie mają ceny albo mają cenę zero.',
            'why' => 'Odwiedzający nie wie, ile to kosztuje, a produktu nie da się pokazać w wynikach zakupowych.',
            'fix' => 'Ustaw cenę albo zdejmij ze strony produkty, które jeszcze nie są w sprzedaży.',
        ],
    ],
];
