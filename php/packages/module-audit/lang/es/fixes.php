<?php

declare(strict_types=1);

return [
    'audit' => [
        'replace-host' => [
            'title' => 'Sustituir el entorno de pruebas por este sitio',
            'description' => 'Cada dirección del entorno de desarrollo en este campo pasa a ser una dirección de este sitio. El registro se guarda a través de su propio módulo, así que el cambio queda en el historial y se puede deshacer.',
        ],
    ],
];
