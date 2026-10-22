<?php

declare(strict_types=1);

return [
    'actions' => [
        'publish' => 'Publicar',
        'unpublish' => 'Retirar del sitio',
        'set-category' => 'Fijar la categoría principal',
        'add-category' => 'Añadir una categoría',
        'remove-category' => 'Quitar una categoría',
        'delete' => 'Eliminar',
        'restore' => 'Restaurar',
    ],
    'params' => [
        'category' => 'Categoría',
    ],
    'errors' => [
        'selection' => 'Elige los productos: una lista de ids o la consulta de la lista.',
        'too-many' => 'Demasiados ids a la vez; envía la consulta de la lista en su lugar.',
        'unknown-action' => 'No existe esa acción masiva. Conocidas: :known',
        'forbidden' => 'Esta acción necesita el permiso :permission.',
        'gone' => 'El producto ya no está donde lo encontró la ejecución.',
    ],
];
