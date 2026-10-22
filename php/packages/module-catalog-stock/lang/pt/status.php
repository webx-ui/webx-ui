<?php

declare(strict_types=1);

return [
    'title' => 'Nome',
    'code' => 'Código',
    'code-help' => 'Letras latinas, algarismos e hífenes: o estado no endereço do filtro (stock_in-stock) e nos modelos. Gerado a partir do nome se ficar vazio.',
    'color' => 'Tom',
    'color-help' => 'O site pinta a linha do estado neste tom; o painel mostra uma etiqueta do mesmo tom.',
    'tones' => [
        'neutral' => 'Neutro',
        'primary' => 'Principal',
        'success' => 'Sucesso',
        'warning' => 'Aviso',
        'danger' => 'Perigo',
        'info' => 'Informação',
    ],
    'purchasable' => 'Pode ser comprado',
    'purchasable-help' => 'Desligado, um produto neste estado não pode ser comprado, e o nome do estado é o que o site mostra em vez do botão de compra.',
    'default' => 'Predefinido',
    'default-help' => 'O estado de todos os produtos aos quais ninguém definiu um estado, e de um produto novo. Para o mudar, torne outro estado o predefinido.',
    'filterable' => 'No filtro',
    'filterable-help' => 'Desligado, o estado não é uma opção do filtro do catálogo.',
    'new' => 'Novo estado',
    'empty' => 'Ainda não há estados de stock.',
    'empty-help' => 'Um estado diz se um produto está em stock e se pode ser comprado.',
    'order' => 'A ordem aqui é a ordem do filtro',
    'hidden' => 'Fora do filtro',
    'count' => 'Produtos: :count',
    'show-items' => 'Mostrar os seus produtos',
    'delete-blocked' => 'Enquanto houver produtos neste estado, não pode ser eliminado — passe-os primeiro para outro estado.',
    'delete-text' => 'Vai para a reciclagem e sai do filtro.',
    'deleted' => 'O estado está na reciclagem.',
    'saved' => 'O estado foi guardado.',
];
