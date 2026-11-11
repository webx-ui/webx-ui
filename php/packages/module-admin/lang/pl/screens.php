<?php

declare(strict_types=1);

return [
    'unknown' => 'Nie ma ekranu o nazwie :name.',
    'row' => 'Wiersz :number: :message',
    'row-shape' => 'Ten wiersz nie jest zestawem pól.',
    'row-unknown-key' => 'Ten wiersz nie ma pola „:key”. Wiersz przyjmuje: :keys.',
    'icon-unknown' => 'W tym zestawie nie ma ikony „:name”. Zestaw zawiera: :names.',
    'icon-source-unknown' => 'Ta strona nie ma zestawu ikon „:source”.',
    'embed-source-unknown' => 'To pole przyjmuje ramki „:source”, a strona nie ma takiej usługi.',
    // The card a module's screen keeps for the fields a project patches in (`project-fields`).
    'project-fields' => 'Więcej',
    // The words of a list of records on a described screen; the core has English only.
    'repeater' => [
        'add' => 'Dodaj',
        'remove' => 'Usuń',
        'remove-question' => 'Usunąć ten wiersz?',
        'cancel' => 'Anuluj',
        'reorder' => 'Zmień kolejność',
        'empty' => 'Na razie nic tu nie ma',
    ],
    'not-a-language-map' => ':field jest tłumaczone: wyślij wartość dla każdego języka, { "en": … }, a nie jedną listę.',
];
