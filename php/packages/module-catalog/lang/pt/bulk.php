<?php

declare(strict_types=1);

return [
    'actions' => [
        'publish' => 'Publicar',
        'unpublish' => 'Retirar do site',
        'set-category' => 'Definir a categoria principal',
        'add-category' => 'Adicionar uma categoria',
        'remove-category' => 'Remover uma categoria',
        'delete' => 'Eliminar',
        'restore' => 'Restaurar',
    ],
    'params' => [
        'category' => 'Categoria',
    ],
    'errors' => [
        'selection' => 'Escolha os produtos: uma lista de ids ou a consulta da lista.',
        'too-many' => 'Demasiados ids de uma vez; envie antes a consulta da lista.',
        'unknown-action' => 'Não existe essa ação em massa. Conhecidas: :known',
        'forbidden' => 'Esta ação requer a permissão :permission.',
        'gone' => 'O produto já não está onde a execução o encontrou.',
    ],
];
