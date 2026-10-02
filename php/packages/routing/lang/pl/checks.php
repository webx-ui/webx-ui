<?php

declare(strict_types=1);

return [
    'routing' => [
        'orphan' => [
            'title' => 'Adresy usuniętych rekordów',
            'found' => 'Wiersz rejestru adresów wskazuje rekord, który już nie istnieje.',
            'why' => 'Adres odpowiada 404, a nadal zajmuje swoją nazwę, więc nowy rekord nie może jej przejąć.',
            'fix' => 'Uruchom php artisan webx:routes:rebuild albo przywróć rekord, jeśli usunięto go przez pomyłkę.',
        ],
        'alias_broken' => [
            'title' => 'Stare adresy, które donikąd nie prowadzą',
            'found' => 'Alias — stary adres zachowany po zmianie sluga — nie prowadzi do żadnego adresu albo prowadzi do innego aliasu.',
            'why' => 'Odwiedzający ze starym linkiem dostaje 404 albo przekierowanie do przekierowania.',
            'fix' => 'Uruchom php artisan webx:routes:rebuild albo usuń alias na karcie „Automatyczne” w sekcji SEO.',
        ],
        'shadowed' => [
            'title' => 'Adresy, które aplikacja obsługuje sama',
            'found' => 'Trasa aplikacji ma ten sam adres co rekord rejestru.',
            'why' => 'Rekord nigdy się nie pokazuje: pierwsza odpowiada trasa aplikacji.',
            'fix' => 'Zmień slug rekordu albo trasę aplikacji.',
        ],
        'no_address' => [
            'title' => 'Rekordy bez adresu',
            'found' => 'Rekord, który powinien mieć adres w jakimś języku, go nie ma.',
            'why' => 'Strony nie można otworzyć, nie ma jej w mapie witryny i nie można do niej linkować.',
            'fix' => 'Zapisz rekord ponownie albo uruchom php artisan webx:routes:rebuild.',
        ],
        'unknown_type' => [
            'title' => 'Adresy modułu, który nie jest zainstalowany',
            'found' => 'Rejestr zawiera adresy typu, którego nie zna żaden zainstalowany moduł.',
            'why' => 'Nie odpowiadają niczym, a wciąż zajmują swoje nazwy przed każdym nowym rekordem.',
            'fix' => 'Usuń te wiersze albo zainstaluj moduł ponownie.',
        ],
    ],
];
