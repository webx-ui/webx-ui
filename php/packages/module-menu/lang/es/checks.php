<?php

declare(strict_types=1);

return [
    'menu' => [
        'broken' => [
            'title' => 'Elementos del menú que llevan a un error',
            'found' => 'Un elemento del menú lleva a una página que responde con un error, o a un registro que ya no tiene dirección.',
            'why' => 'El menú está en todas las páginas: un elemento roto es un enlace roto en todas partes, y lo primero que pulsa un visitante.',
            'fix' => 'Apunta el elemento a una página que exista, o elimínalo.',
        ],
        'redirect' => [
            'title' => 'Elementos del menú que llevan a una redirección',
            'found' => 'Un elemento del menú lleva a una dirección que redirige a otro lugar.',
            'why' => 'Cada clic cuesta un viaje de ida y vuelta extra, en todas las páginas en las que se imprime el menú.',
            'fix' => 'Apunta el elemento a la dirección en la que termina.',
        ],
    ],
];
