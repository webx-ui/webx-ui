<?php

declare(strict_types=1);

return [
    'routing' => [
        'orphan' => [
            'title' => 'Direcciones de registros eliminados',
            'found' => 'Una fila del registro de direcciones apunta a un registro que ya no existe.',
            'why' => 'La dirección responde 404 mientras conserva su nombre, así que un registro nuevo no puede usarlo.',
            'fix' => 'Ejecuta php artisan webx:routes:rebuild, o restaura el registro si se eliminó por error.',
        ],
        'alias_broken' => [
            'title' => 'Direcciones antiguas que no llevan a ninguna parte',
            'found' => 'Un alias —la dirección antigua que se conserva tras cambiar un slug— no lleva a ninguna dirección, o lleva a otro alias.',
            'why' => 'Un visitante con un enlace antiguo recibe un 404, o una redirección a una redirección.',
            'fix' => 'Ejecuta php artisan webx:routes:rebuild, o elimina el alias en la pestaña «Automáticas» de la sección SEO.',
        ],
        'shadowed' => [
            'title' => 'Direcciones que responde la propia aplicación',
            'found' => 'Una ruta de la aplicación tiene la misma dirección que un registro del registro de direcciones.',
            'why' => 'El registro nunca se muestra: la ruta de la aplicación responde primero.',
            'fix' => 'Cambia el slug del registro o la ruta de la aplicación.',
        ],
        'no_address' => [
            'title' => 'Registros sin dirección',
            'found' => 'Un registro que debería tener una dirección en un idioma no la tiene.',
            'why' => 'La página no se puede abrir, no está en el sitemap y no se puede enlazar.',
            'fix' => 'Guarda el registro de nuevo, o ejecuta php artisan webx:routes:rebuild.',
        ],
        'unknown_type' => [
            'title' => 'Direcciones de un módulo que no está instalado',
            'found' => 'El registro contiene direcciones de un tipo que ningún módulo instalado conoce.',
            'why' => 'No responden nada y aun así conservan sus nombres frente a cada registro nuevo.',
            'fix' => 'Elimina esas filas, o instala el módulo de nuevo.',
        ],
    ],
];
