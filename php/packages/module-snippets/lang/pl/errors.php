<?php

declare(strict_types=1);

return [
    'required' => 'Pole „:field” nie może być puste.',
    'name-long' => 'Nazwa jest dłuższa niż :max znaków.',
    'code-long' => 'Kod ma :bytes bajtów; limit to :max.',
    'consent' => 'Kategoria zgody to jedna z: :categories.',
    'mask' => 'Ścieżka zaczyna się od katalogu głównego, np. „/blog/*”, bez adresu strony i „?”: [:mask] nie jest ścieżką.',
    'masks-many' => 'Nie więcej niż :max ścieżek na liście.',
    'locales' => 'Strona nie jest publikowana w :unknown. Ma: :known.',
    'boolean' => 'Pole „:field” to tak albo nie.',
    'list' => 'Oczekiwana jest lista ścieżek lub języków.',
    'place-undeclared' => 'Miejsce „:place” nie jest zadeklarowane w config/webx-snippets.php.',
    'place-orphaned' => 'Miejsce „:place” nie jest już zadeklarowane, kod nie wyświetlałby się nigdzie: przenieś go albo wyłącz.',
    'document-tags' => 'Kod zawiera :tags: wklejaj tylko to, co serwis każe umieścić wewnątrz strony.',
    'unknown' => 'Nie ma kodu o id :ids.',
    'reorder' => 'Kolejność to miejsce i lista jego kodów.',
];
