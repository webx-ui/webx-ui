<?php

declare(strict_types=1);

return [
    'actions' => [
        'publish' => 'Opublikuj',
        'unpublish' => 'Zdejmij ze strony',
        'set-category' => 'Ustaw kategorię główną',
        'add-category' => 'Dodaj kategorię',
        'remove-category' => 'Usuń z kategorii',
        'delete' => 'Usuń',
        'restore' => 'Przywróć',
    ],
    'params' => [
        'category' => 'Kategoria',
    ],
    'errors' => [
        'selection' => 'Wybierz produkty: listę identyfikatorów lub zapytanie listy.',
        'too-many' => 'Za dużo identyfikatorów naraz; wyślij zamiast tego zapytanie listy.',
        'unknown-action' => 'Nie ma takiej akcji zbiorczej. Znane: :known',
        'forbidden' => 'Ta akcja wymaga uprawnienia :permission.',
        'gone' => 'Produktu nie ma już tam, gdzie znalazło go zadanie.',
    ],
];
