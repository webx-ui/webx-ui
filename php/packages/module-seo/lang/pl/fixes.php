<?php

declare(strict_types=1);

return [
    'seo' => [
        'normalise-host' => [
            'title' => 'Przekieruj na główne lustro',
            'description' => 'Włącza „Główne lustro” w ustawieniach SEO: druga nazwa odpowiada jednym przekierowaniem 301 na tę.',
        ],
        'normalise-https' => [
            'title' => 'Przekieruj na https',
            'description' => 'Włącza „Zawsze https” w ustawieniach SEO: adres otwarty przez http odpowiada jednym przekierowaniem 301 na https.',
        ],
        'normalise-slashes' => [
            'title' => 'Scalaj podwójne ukośniki',
            'description' => 'Włącza „Scalaj podwójne ukośniki” w ustawieniach SEO.',
        ],
        'normalise-index' => [
            'title' => 'Obcinaj pliki index',
            'description' => 'Włącza „Obcinaj pliki index” w ustawieniach SEO: /index.php i /index.html przekierowują na folder.',
        ],
        'normalise-trailing' => [
            'title' => 'Jedna forma ukośnika na końcu',
            'description' => 'Ustawia „Ukośnik na końcu” w ustawieniach SEO na formę, której używają własne linki witryny.',
        ],
        'normalise-case' => [
            'title' => 'Przekieruj na małe litery',
            'description' => 'Włącza „Małe litery” w ustawieniach SEO: /About odpowiada jednym przekierowaniem 301 na /about.',
        ],
        'collapse-chain' => [
            'title' => 'Zwiń łańcuch',
            'description' => 'Kieruje każde dokładne przekierowanie łańcucha od razu na adres, na którym się on kończy.',
        ],
        'robots-sitemap' => [
            'title' => 'Dodaj wiersz Sitemap',
            'description' => 'Zapisuje w robots.txt w ustawieniach SEO wiersz Sitemap: z adresem mapy strony.',
        ],
    ],
];
