<?php

declare(strict_types=1);

return [
    'seo' => [
        'normalise-host' => [
            'title' => 'Redirigir al espejo principal',
            'description' => 'Activa «Espejo principal» en los ajustes SEO: el otro nombre responde con una sola 301 a este.',
        ],
        'normalise-https' => [
            'title' => 'Redirigir a https',
            'description' => 'Activa «Siempre https» en los ajustes SEO: una dirección abierta por http responde con una sola 301 a https.',
        ],
        'normalise-slashes' => [
            'title' => 'Colapsar las barras dobles',
            'description' => 'Activa «Colapsar barras dobles» en los ajustes SEO.',
        ],
        'normalise-index' => [
            'title' => 'Quitar los archivos index',
            'description' => 'Activa «Quitar archivos index» en los ajustes SEO: /index.php y /index.html redirigen a la carpeta.',
        ],
        'normalise-trailing' => [
            'title' => 'Una sola forma de la barra final',
            'description' => 'Pone «Barra final» en los ajustes SEO en la forma que usan los propios enlaces del sitio.',
        ],
        'normalise-case' => [
            'title' => 'Redirigir a minúsculas',
            'description' => 'Activa «Minúsculas» en los ajustes SEO: /About responde con una sola 301 a /about.',
        ],
        'collapse-chain' => [
            'title' => 'Colapsar la cadena',
            'description' => 'Apunta cada redirección exacta de la cadena directamente a la dirección en la que termina.',
        ],
        'robots-sitemap' => [
            'title' => 'Añadir la línea Sitemap',
            'description' => 'Escribe en robots.txt, en los ajustes SEO, la línea Sitemap: con la dirección del mapa del sitio.',
        ],
    ],
];
