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
    'blocks' => [
        'stray_values' => [
            'title' => 'Valores de bloco para campos que o tipo não tem',
            'found' => 'Há blocos com valores de campos que o seu tipo não define — restos de uma importação ou de um campo retirado do tipo.',
            'why' => 'O visitante não os vê, mas estão nos dados do editor e no que um agente lê, e reaparecem como uma etiqueta errada do bloco.',
            'fix' => 'Retire-os com a correção ou para todo o site com php artisan webx:blocks:prune. Um bloco de um tipo que já não existe não é tocado. Os itens de um repetidor são comparados com os seus campos.',
        ],
    ],
    'syntax' => 'O modelo não compila: :reason. A publicação será recusada.',
    'unknown-field-type' => 'Campos de um tipo que o site não conhece: :fields. O formulário mostra um aviso no lugar deles e ninguém verifica os valores.',
    'field-id' => 'O id de um campo são letras, dígitos, _ e -, começando por uma letra: :ids.',
];
