<?php

declare(strict_types=1);

return [
    'no-marker' => 'No hay data-wx-block en la raíz: el script no se ejecutará y el panel no podrá resaltar el bloque en la vista previa.',
    'stray-selectors' => 'Selectores fuera del prefijo del bloque .b-:slug: :selectors',
    'bare-selectors' => 'Los selectores de elemento alcanzan todo el sitio: :selectors',
    'media-query' => '@media mide la ventana. Un bloque se dimensiona por su contenedor: use @container.',
    'variables-missing' => 'La plantilla usa :variables, que el esquema no declara. La publicación será rechazada.',
    'ok-marker' => 'La raíz lleva data-wx-block.',
    'ok-prefix' => 'Todos los selectores empiezan por .b-:slug.',
    'ok-bare' => 'No hay selectores de elemento sueltos.',
    'ok-container' => 'El ancho lo deciden las consultas de contenedor.',
    'ok-variables' => 'Todas las variables de la plantilla son campos del esquema.',
    'blocks' => [
        'stray_values' => [
            'title' => 'Valores de bloque para campos que el tipo no tiene',
            'found' => 'Hay bloques con valores de campos que su tipo no define, restos de una importación o de un campo quitado del tipo.',
            'why' => 'El visitante no los ve, pero están en los datos del editor y en lo que lee un agente, y aparecen como una etiqueta equivocada del bloque.',
            'fix' => 'Quítelos con la corrección o en todo el sitio con php artisan webx:blocks:prune. Un bloque de un tipo que ya no existe no se toca. Los elementos de un repetidor se comparan con sus campos.',
        ],
    ],
];
