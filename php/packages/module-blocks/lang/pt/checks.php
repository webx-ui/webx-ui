<?php

declare(strict_types=1);

return [
    'no-marker' => 'Não há data-wx-block na raiz: o script não corre e o painel não consegue realçar o bloco na pré-visualização.',
    'stray-selectors' => 'Seletores fora do prefixo do bloco .b-:slug: :selectors',
    'bare-selectors' => 'Seletores de elemento atingem o site inteiro: :selectors',
    'media-query' => '@media mede a janela. Um bloco dimensiona-se pelo seu contentor: use @container.',
    'string-on-text' => 'Com um shortcode dentro, :field é HTML: uma função de string ou um cast entrega esse HTML a {{ }}, que o escapa uma segunda vez. Altere-o através de wx_text(): {{ wx_text(:field)->trimEnd(".") }} — trim, trimStart, stripPrefix, stripSuffix e map() mantêm-no HTML.',
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
        'unknown_shortcodes' => [
            'title' => 'Shortcodes digitados errado',
            'found' => 'Um texto tem um colchete que é quase um shortcode do site, ou que tem argumentos, e não é um.',
            'why' => 'Só os shortcodes registrados são substituídos. O resto é impresso como foi digitado, com colchetes, à vista de todos os visitantes.',
            'fix' => 'Corrija o nome para o sugerido, ou escreva [[nome]] se a página deve mostrar os colchetes. A lista está na ajuda de shortcodes do campo e em «Configurações» → «Shortcodes».',
        ],
        'hardcoded_values' => [
            'title' => 'Valores em vez de um shortcode',
            'found' => 'Um texto contém um telefone, um e-mail ou outro valor que um shortcode de «Configurações» → «Shortcodes» já guarda.',
            'why' => 'Hoje está certo, errado no dia em que o valor mudar: o shortcode muda em todo lugar, um valor digitado à mão só onde alguém se lembrar.',
            'fix' => 'Substitua o valor pelo shortcode sugerido, p. ex. [phone]. O link é criado pelo próprio shortcode.',
        ],
    ],
    'syntax' => 'O modelo não compila: :reason. A publicação será recusada.',
    'unknown-field-type' => 'Campos de um tipo que o site não conhece: :fields. O formulário mostra um aviso no lugar deles e ninguém verifica os valores.',
    'field-id' => 'O id de um campo são letras, dígitos, _ e -, começando por uma letra: :ids.',
    'marker-slug' => 'A raiz está marcada com data-wx-block=":marker", mas o identificador é «:slug»: o script e o painel encontram o bloco pelo identificador exato. A publicação será recusada.',
];
