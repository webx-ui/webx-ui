<?php

declare(strict_types=1);

return [
    'title' => 'Nombre',
    'code' => 'Código',
    'code-help' => 'Letras latinas, dígitos y guiones: el estado en la dirección del filtro (stock_in-stock) y en las plantillas. Se genera a partir del nombre si se deja vacío.',
    'color' => 'Tono',
    'color-help' => 'El sitio pinta la línea del estado en este tono; el panel muestra una marca del mismo tono.',
    'tones' => [
        'neutral' => 'Neutro',
        'primary' => 'Principal',
        'success' => 'Éxito',
        'warning' => 'Aviso',
        'danger' => 'Peligro',
        'info' => 'Información',
    ],
    'purchasable' => 'Se puede comprar',
    'purchasable-help' => 'Desactivado, un producto en este estado no se puede comprar, y su nombre es lo que el sitio muestra en lugar del botón de compra.',
    'default' => 'Predeterminado',
    'default-help' => 'El estado de todo producto al que nadie le fijó estado, y de un producto nuevo. Para cambiarlo, haz predeterminado otro estado.',
    'filterable' => 'En el filtro',
    'filterable-help' => 'Desactivado, el estado no es una opción del filtro del catálogo.',
    'new' => 'Nuevo estado',
    'empty' => 'Aún no hay estados de existencias.',
    'empty-help' => 'Un estado indica si un producto está en existencias y si se puede comprar.',
    'order' => 'El orden aquí es el orden del filtro',
    'hidden' => 'Fuera del filtro',
    'count' => 'Productos: :count',
    'show-items' => 'Mostrar sus productos',
    'delete-blocked' => 'Mientras haya productos en él, no se puede eliminar — muévelos antes a otro estado.',
    'delete-text' => 'Va a la papelera y sale del filtro.',
    'deleted' => 'El estado está en la papelera.',
    'saved' => 'El estado está guardado.',
];
