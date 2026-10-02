<?php

declare(strict_types=1);

return [
    'seo' => [
        'normalise-host' => [
            'title' => 'Reindirizzare al mirror principale',
            'description' => 'Attiva «Mirror principale» nelle impostazioni SEO: l’altro nome risponde con un solo 301 verso questo.',
        ],
        'normalise-https' => [
            'title' => 'Reindirizzare a https',
            'description' => 'Attiva «Sempre https» nelle impostazioni SEO: un indirizzo aperto su http risponde con un solo 301 verso https.',
        ],
        'normalise-slashes' => [
            'title' => 'Unire le doppie barre',
            'description' => 'Attiva «Unisci le doppie barre» nelle impostazioni SEO.',
        ],
        'normalise-index' => [
            'title' => 'Eliminare i file index',
            'description' => 'Attiva «Elimina i file index» nelle impostazioni SEO: /index.php e /index.html reindirizzano alla cartella.',
        ],
        'normalise-trailing' => [
            'title' => 'Una sola forma della barra finale',
            'description' => 'Imposta «Barra finale» nelle impostazioni SEO sulla forma usata dai link del sito stesso.',
        ],
        'normalise-case' => [
            'title' => 'Reindirizzare alle minuscole',
            'description' => 'Attiva «Minuscole» nelle impostazioni SEO: /About risponde con un solo 301 verso /about.',
        ],
        'collapse-chain' => [
            'title' => 'Accorciare la catena',
            'description' => 'Fa puntare ogni reindirizzamento esatto della catena direttamente all’indirizzo in cui finisce.',
        ],
        'robots-sitemap' => [
            'title' => 'Aggiungere la riga Sitemap',
            'description' => 'Scrive la riga Sitemap: con l’indirizzo della sitemap in robots.txt, nelle impostazioni SEO.',
        ],
    ],
];
