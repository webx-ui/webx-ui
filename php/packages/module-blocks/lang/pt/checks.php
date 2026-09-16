<?php

declare(strict_types=1);

return [
    'no-marker' => 'Não há data-wx-block na raiz: o script não corre e o painel não consegue realçar o bloco na pré-visualização.',
    'stray-selectors' => 'Seletores fora do prefixo do bloco .b-:slug: :selectors',
    'bare-selectors' => 'Seletores de elemento atingem o site inteiro: :selectors',
    'media-query' => '@media mede a janela. Um bloco dimensiona-se pelo seu contentor: use @container.',
    'variables-missing' => 'O modelo usa :variables, que o esquema não declara. A publicação será recusada.',
    'ok-marker' => 'A raiz tem data-wx-block.',
    'ok-prefix' => 'Todos os seletores começam por .b-:slug.',
    'ok-bare' => 'Sem seletores de elemento soltos.',
    'ok-container' => 'A largura é decidida por consultas de contentor.',
    'ok-variables' => 'Todas as variáveis do modelo são campos do esquema.',
];
