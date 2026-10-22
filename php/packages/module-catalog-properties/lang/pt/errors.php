<?php

declare(strict_types=1);

return [
    'type' => 'Uma propriedade é uma de: :types.',
    'type-fixed' => 'O tipo de uma propriedade não muda depois de guardada: crie uma propriedade nova e passe para lá os valores.',
    'code' => 'Letras latinas, algarismos e hífenes simples, no máximo :max caracteres.',
    'code-taken' => 'Este código já está ocupado neste idioma por: :holder.',
    'slug' => 'Letras latinas, algarismos e hífenes simples.',
    'slug-taken' => 'Outro valor desta propriedade tem este slug neste idioma.',
    'color' => 'Uma cor é #rrggbb.',
    'group' => 'Esse grupo não existe.',
    'group-in-use' => 'O grupo contém propriedades. Propriedades: :count',
    'unknown-property' => 'Essa propriedade não existe.',
    'inherited' => '«:property» já está no conjunto de «:category», mais acima; uma categoria herda-a.',
    'outside-set' => 'Esta propriedade não está no conjunto da categoria principal do produto.',
    'number' => 'É esperado um número.',
    'text' => 'É esperado um texto, ou um mapa de idiomas.',
    'one-value' => 'Esta propriedade aceita um só valor.',
    'unknown-value' => 'Esta propriedade não tem esse valor.',
    'unknown-slug' => '«:property» não tem o valor «:slug». Crie-o primeiro com catalog_property_values_create, ou indique o id.',
    'leaves-only' => 'Aqui só pode ser escolhido um valor que não tenha nada abaixo dele.',
    'value-in-use' => 'Há produtos com este valor — em vez disso, junte-o a outro. Produtos: :count',
    'values-select' => 'Só uma lista de referência tem valores.',
    'not-tree' => 'Os valores desta propriedade não estão em árvore.',
    'move-into-itself' => 'Um valor não pode ficar abaixo de si próprio.',
    'merge-other' => 'Um valor junta-se a outro valor da mesma propriedade.',
    'merge-descendant' => 'Um valor não pode juntar-se a um que está abaixo dele.',
    'intervals-number' => 'Só um número tem intervalos.',
    'interval-ends' => 'O limite superior tem de estar acima do inferior.',
];
