<?php

declare(strict_types=1);

return [
    'routing' => [
        'orphan' => [
            'title' => 'Endereços de registos eliminados',
            'found' => 'Uma linha do registo de endereços aponta para um registo que já não existe.',
            'why' => 'O endereço responde 404 enquanto mantém o seu nome, por isso um registo novo não o pode usar.',
            'fix' => 'Execute php artisan webx:routes:rebuild, ou restaure o registo se foi eliminado por engano.',
        ],
        'alias_broken' => [
            'title' => 'Endereços antigos que não levam a lado nenhum',
            'found' => 'Um alias — o endereço antigo guardado depois de um slug mudar — não leva a nenhum endereço, ou leva a outro alias.',
            'why' => 'Um visitante com um link antigo recebe um 404, ou um redirecionamento para um redirecionamento.',
            'fix' => 'Execute php artisan webx:routes:rebuild, ou elimine o alias no separador «Automáticos» da secção SEO.',
        ],
        'shadowed' => [
            'title' => 'Endereços a que a própria aplicação responde',
            'found' => 'Uma rota da aplicação tem o mesmo endereço que um registo do registo de endereços.',
            'why' => 'O registo nunca é mostrado: a rota da aplicação responde primeiro.',
            'fix' => 'Altere o slug do registo, ou a rota da aplicação.',
        ],
        'no_address' => [
            'title' => 'Registos sem endereço',
            'found' => 'Um registo que devia ter um endereço num idioma não o tem.',
            'why' => 'A página não pode ser aberta, não está no sitemap e não pode ser ligada por link.',
            'fix' => 'Guarde o registo de novo, ou execute php artisan webx:routes:rebuild.',
        ],
        'unknown_type' => [
            'title' => 'Endereços de um módulo que não está instalado',
            'found' => 'O registo contém endereços de um tipo que nenhum módulo instalado conhece.',
            'why' => 'Não respondem a nada e ainda assim mantêm os seus nomes contra cada registo novo.',
            'fix' => 'Remova essas linhas, ou instale o módulo de novo.',
        ],
    ],
];
