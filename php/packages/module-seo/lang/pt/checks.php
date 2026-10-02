<?php

declare(strict_types=1);

return [
    'seo' => [
        'redirect_chain' => [
            'title' => 'Redirecionamentos que levam a redirecionamentos',
            'found' => 'Um redirecionamento da tabela SEO aponta para um endereço que por sua vez é redirecionado.',
            'why' => 'Cada salto é mais uma ida e volta para o visitante, e os motores de busca deixam de os seguir ao fim de alguns.',
            'fix' => 'Aponte o primeiro redirecionamento diretamente para o último endereço — o botão de correção fá-lo para cada redirecionamento exato da cadeia.',
        ],
        'title_duplicate' => [
            'title' => 'O mesmo título em várias fichas SEO',
            'found' => 'Várias entidades têm o mesmo título escrito na sua ficha SEO, no mesmo idioma.',
            'why' => 'Duas páginas que se chamam o mesmo competem entre si na pesquisa, e nenhuma parece a resposta.',
            'fix' => 'Dê a cada página um título que diga o que nela está e em mais lado nenhum.',
        ],
        'redirect_broken' => [
            'title' => 'Redirecionamentos para uma página quebrada',
            'found' => 'Um redirecionamento exato envia os visitantes para um endereço que respondeu com um erro durante o rastreio.',
            'why' => 'O visitante que seguiu um link antigo acaba numa página de erro, e perde-se o peso do endereço antigo.',
            'fix' => 'Aponte o redirecionamento para uma página que exista, ou restaure a página.',
        ],
        'rule_dead' => [
            'title' => 'Regras SEO de endereços que já não existem',
            'found' => 'Há uma regra SEO exata escrita para um endereço que respondeu 404 ou 410 durante o rastreio.',
            'why' => 'Nada de prejudicial, mas a regra não serve ninguém e esconde que a página para a qual foi escrita desapareceu.',
            'fix' => 'Elimine a regra, ou adicione um redirecionamento a partir desse endereço se a página mudou de sítio.',
        ],
    ],
];
