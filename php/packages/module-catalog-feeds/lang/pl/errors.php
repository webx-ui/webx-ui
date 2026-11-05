<?php

declare(strict_types=1);

return [
    'currency' => 'Feed używa waluty witryny: :currency.',
    'currency-unset' => 'Witryna nie ma waluty: ustaw WEBX_CATALOG_CURRENCY.',
    'filter-facet' => 'Filtr feedu wskazuje [:facet], którego na stronie nie ma.',
    'source-property' => 'Feed bierze MPN z właściwości [:property], której na stronie nie ma.',
    'no-item' => 'Feed nie ma szablonu produktu.',
    'template' => 'Nie można odczytać szablonu: :reason',
    'too-large' => 'Plik przekracza dopuszczalny rozmiar feedu.',
    'stale' => 'Budowanie przestało postępować i zostało zamknięte.',
    'file-xml' => 'Plik nie jest poprawnym XML, wiersz :line: :reason',
    'file-json' => 'Nagłówek i stopka nie tworzą poprawnego JSON: :reason',
    'file-json-item' => 'Produkt :product nie tworzy poprawnego JSON: :reason',
    'file-csv' => 'Wiersz :line pliku ma pól: :found zamiast :expected.',
    'sample-json' => 'Próbka nie jest poprawnym JSON, wiersz :line: :reason',
    'sample-place' => 'Część „:part”, wiersz :line: :fragment',
    'unknown-variable' => 'W części „:part” nie ma zmiennej {{:name}}.',
    'no-products' => 'Żaden produkt jeszcze nie pasuje do feedu: sprawdzono tylko nagłówek i stopkę.',
];
