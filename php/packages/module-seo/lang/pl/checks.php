<?php

declare(strict_types=1);

return [
    'seo' => [
        'redirect_chain' => [
            'title' => 'Przekierowania, które prowadzą do przekierowań',
            'found' => 'Przekierowanie w tabeli SEO wskazuje adres, który sam jest przekierowywany dalej.',
            'why' => 'Każdy skok to kolejna runda dla odwiedzającego, a wyszukiwarki przestają podążać za łańcuchem po kilku krokach.',
            'fix' => 'Skieruj pierwsze przekierowanie od razu na ostatni adres — przycisk naprawy robi to dla każdego dokładnego przekierowania łańcucha.',
        ],
        'title_duplicate' => [
            'title' => 'Ten sam tytuł w kilku kartach SEO',
            'found' => 'Kilka encji ma w karcie SEO zapisany ten sam tytuł w tym samym języku.',
            'why' => 'Dwie strony, które nazywają się tak samo, konkurują ze sobą w wyszukiwarce i żadna nie wygląda jak odpowiedź.',
            'fix' => 'Nadaj każdej stronie tytuł, który mówi, co na niej jest, i nigdzie indziej.',
        ],
        'redirect_broken' => [
            'title' => 'Przekierowania na zepsutą stronę',
            'found' => 'Dokładne przekierowanie kieruje odwiedzających na adres, który podczas skanowania odpowiedział błędem.',
            'why' => 'Odwiedzający, który wszedł starym linkiem, trafia na stronę błędu, a waga starego adresu przepada.',
            'fix' => 'Skieruj przekierowanie na istniejącą stronę albo przywróć stronę.',
        ],
        'rule_dead' => [
            'title' => 'Reguły SEO dla adresów, których już nie ma',
            'found' => 'Dokładna reguła SEO jest napisana dla adresu, który podczas skanowania odpowiedział 404 lub 410.',
            'why' => 'Nic złego, ale reguła jest dla nikogo i ukrywa fakt, że strona, dla której ją napisano, zniknęła.',
            'fix' => 'Usuń regułę albo dodaj przekierowanie z tego adresu, jeśli strona się przeniosła.',
        ],
    ],
];
