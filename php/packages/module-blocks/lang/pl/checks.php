<?php

declare(strict_types=1);

return [
    'no-marker' => 'Brak data-wx-block na korzeniu: skrypt się nie uruchomi, a panel nie podświetli bloku w podglądzie.',
    'stray-selectors' => 'Selektory poza prefiksem bloku .b-:slug: :selectors',
    'bare-selectors' => 'Selektory elementów sięgają całej strony: :selectors',
    'media-query' => '@media mierzy okno. O szerokości bloku decyduje kontener: użyj @container.',
    'variables-missing' => 'Szablon używa :variables, których schemat nie deklaruje. Publikacja zostanie odrzucona.',
    'ok-marker' => 'Korzeń ma data-wx-block.',
    'ok-prefix' => 'Każdy selektor zaczyna się od .b-:slug.',
    'ok-bare' => 'Brak gołych selektorów elementów.',
    'ok-container' => 'O szerokości decydują zapytania kontenera.',
    'ok-variables' => 'Każda zmienna szablonu jest polem schematu.',
];
