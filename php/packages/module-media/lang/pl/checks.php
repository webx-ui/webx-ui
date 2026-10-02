<?php

declare(strict_types=1);

return [
    'media' => [
        'missing_file' => [
            'title' => 'Pliki biblioteki, których nie ma na dysku',
            'found' => 'Biblioteka mediów wymienia plik, którego dysk nie ma.',
            'why' => 'Każda strona i pole, które go używają, pokazują zepsuty obrazek albo martwy link do pobrania.',
            'fix' => 'Wgraj plik ponownie w bibliotece mediów albo skopiuj folder storage stamtąd, skąd pochodzi witryna.',
        ],
        'heavy' => [
            'title' => 'Obrazki zbyt ciężkie dla strony',
            'found' => 'Obrazki w bibliotece mediów ważą więcej niż limit.',
            'why' => 'Strona, która taki pokazuje, wolno się ładuje na telefonie, a wyszukiwarki oceniają wolne strony niżej.',
            'fix' => 'Zamień je na mniejsze wersje: zdjęcie na stronę rzadko musi być szersze niż 2000 pikseli ani cięższe niż kilkaset kilobajtów.',
        ],
    ],
];
