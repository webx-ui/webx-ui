<?php

declare(strict_types=1);

return [
    'menu' => [
        'broken' => [
            'title' => 'Pozycje menu, które prowadzą do błędu',
            'found' => 'Pozycja menu prowadzi do strony, która odpowiada błędem, albo do rekordu, który nie ma już adresu.',
            'why' => 'Menu jest na każdej stronie: jedna zepsuta pozycja to zepsuty link wszędzie i pierwsze, co klika odwiedzający.',
            'fix' => 'Skieruj pozycję na istniejącą stronę albo usuń ją.',
        ],
        'redirect' => [
            'title' => 'Pozycje menu, które prowadzą do przekierowania',
            'found' => 'Pozycja menu prowadzi do adresu, który przekierowuje gdzie indziej.',
            'why' => 'Każde kliknięcie kosztuje dodatkową rundę, na każdej stronie, na której menu jest wyświetlane.',
            'fix' => 'Skieruj pozycję na adres, na którym kończy.',
        ],
    ],
];
