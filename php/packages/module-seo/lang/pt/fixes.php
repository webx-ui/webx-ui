<?php

declare(strict_types=1);

return [
    'seo' => [
        'normalise-host' => [
            'title' => 'Redirecionar para o espelho principal',
            'description' => 'Ativa «Espelho principal» nas definições de SEO: o outro nome responde com um único 301 para este.',
        ],
        'normalise-https' => [
            'title' => 'Redirecionar para https',
            'description' => 'Ativa «Sempre https» nas definições de SEO: um endereço aberto por http responde com um único 301 para https.',
        ],
        'normalise-slashes' => [
            'title' => 'Colapsar as barras duplas',
            'description' => 'Ativa «Colapsar barras duplas» nas definições de SEO.',
        ],
        'normalise-index' => [
            'title' => 'Cortar os ficheiros index',
            'description' => 'Ativa «Cortar ficheiros index» nas definições de SEO: /index.php e /index.html redirecionam para a pasta.',
        ],
        'normalise-trailing' => [
            'title' => 'Uma só forma da barra final',
            'description' => 'Define «Barra final» nas definições de SEO para a forma que os próprios links do site usam.',
        ],
        'normalise-case' => [
            'title' => 'Redirecionar para minúsculas',
            'description' => 'Ativa «Minúsculas» nas definições de SEO: /About responde com um único 301 para /about.',
        ],
        'collapse-chain' => [
            'title' => 'Colapsar a cadeia',
            'description' => 'Aponta cada redirecionamento exato da cadeia diretamente para o endereço onde ela termina.',
        ],
        'robots-sitemap' => [
            'title' => 'Adicionar a linha Sitemap',
            'description' => 'Escreve no robots.txt, nas definições de SEO, a linha Sitemap: com o endereço do mapa do site.',
        ],
    ],
];
