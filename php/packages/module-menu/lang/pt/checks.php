<?php

declare(strict_types=1);

return [
    'menu' => [
        'broken' => [
            'title' => 'Itens do menu que levam a um erro',
            'found' => 'Um item do menu leva a uma página que responde com um erro, ou a um registo que já não tem endereço.',
            'why' => 'O menu está em todas as páginas: um item quebrado é um link quebrado em todo o lado, e a primeira coisa em que o visitante clica.',
            'fix' => 'Aponte o item para uma página que exista, ou remova-o.',
        ],
        'redirect' => [
            'title' => 'Itens do menu que levam a um redirecionamento',
            'found' => 'Um item do menu leva a um endereço que redireciona para outro lado.',
            'why' => 'Cada clique custa uma ida e volta extra, em todas as páginas em que o menu é impresso.',
            'fix' => 'Aponte o item para o endereço onde ele acaba.',
        ],
    ],
];
