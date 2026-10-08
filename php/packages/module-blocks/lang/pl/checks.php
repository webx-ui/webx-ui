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
    'blocks' => [
        'stray_values' => [
            'title' => 'Wartości bloku dla pól, których typ nie ma',
            'found' => 'Bloki trzymają wartości pól, których ich typ nie definiuje — zostały po imporcie albo po polu usuniętym z typu.',
            'why' => 'Odwiedzający ich nie widzi, ale są w danych edytora i w tym, co czyta agent, i wracają jako błędny podpis bloku.',
            'fix' => 'Usuń je poprawką albo dla całej strony poleceniem php artisan webx:blocks:prune. Blok typu, którego już nie ma, nie jest ruszany. Elementy repeatera są porównywane z jego polami.',
        ],
        'unknown_shortcodes' => [
            'title' => 'Literówki w shortcodach',
            'found' => 'Tekst zawiera nawias, który prawie jest shortcodem strony albo ma argumenty, ale nim nie jest.',
            'why' => 'Zastępowane są tylko zarejestrowane shortcody. Reszta drukuje się tak, jak ją wpisano, z nawiasami — na oczach każdego odwiedzającego.',
            'fix' => 'Popraw nazwę na sugerowaną albo napisz [[nazwa]], jeśli strona ma pokazać nawiasy. Lista jest w pomocy shortcodów pola i w «Ustawienia» → «Shortcody».',
        ],
        'hardcoded_values' => [
            'title' => 'Wartości zamiast shortcodu',
            'found' => 'Tekst zawiera numer telefonu, e-mail lub inną wartość, którą już trzyma shortcode z «Ustawienia» → «Shortcody».',
            'why' => 'Dziś jest poprawna, nieaktualna w dniu zmiany: shortcode zmienia się wszędzie, wartość wpisana ręcznie tylko tam, gdzie ktoś o niej pamięta.',
            'fix' => 'Zastąp wartość sugerowanym shortcodem, np. [phone]. Link tworzy sam shortcode.',
        ],
    ],
    'syntax' => 'Szablon się nie kompiluje: :reason. Publikacja zostanie odrzucona.',
    'unknown-field-type' => 'Pola typu nieznanego stronie: :fields. Formularz pokaże w ich miejscu ostrzeżenie, a wartości nikt nie sprawdzi.',
    'field-id' => 'Identyfikator pola to litery, cyfry, _ i -, na początku litera: :ids.',
    'marker-slug' => 'Korzeń jest oznaczony data-wx-block=":marker", ale identyfikator to «:slug»: skrypt i panel znajdują blok po dokładnym identyfikatorze. Publikacja zostanie odrzucona.',
];
