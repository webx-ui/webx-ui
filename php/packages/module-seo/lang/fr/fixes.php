<?php

declare(strict_types=1);

return [
    'seo' => [
        'normalise-host' => [
            'title' => 'Rediriger vers le miroir principal',
            'description' => 'Active « Miroir principal » dans les réglages SEO : l’autre nom répond par une seule 301 vers celui-ci.',
        ],
        'normalise-https' => [
            'title' => 'Rediriger vers https',
            'description' => 'Active « Toujours https » dans les réglages SEO : une adresse ouverte en http répond par une seule 301 vers https.',
        ],
        'normalise-slashes' => [
            'title' => 'Fusionner les doubles barres obliques',
            'description' => 'Active « Fusionner les doubles barres obliques » dans les réglages SEO.',
        ],
        'normalise-index' => [
            'title' => 'Supprimer les fichiers index',
            'description' => 'Active « Supprimer les fichiers index » dans les réglages SEO : /index.php et /index.html redirigent vers le dossier.',
        ],
        'normalise-trailing' => [
            'title' => 'Une seule forme de la barre oblique à la fin',
            'description' => 'Règle « Barre oblique à la fin » dans les réglages SEO sur la forme qu’utilisent les propres liens du site.',
        ],
        'normalise-case' => [
            'title' => 'Rediriger vers les minuscules',
            'description' => 'Active « Minuscules » dans les réglages SEO : /About répond par une seule 301 vers /about.',
        ],
        'collapse-chain' => [
            'title' => 'Raccourcir la chaîne',
            'description' => 'Fait pointer chaque redirection exacte de la chaîne directement vers l’adresse où elle se termine.',
        ],
        'robots-sitemap' => [
            'title' => 'Ajouter la ligne Sitemap',
            'description' => 'Écrit la ligne Sitemap: avec l’adresse du plan du site dans robots.txt, dans les réglages SEO.',
        ],
    ],
];
