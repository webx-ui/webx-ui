<?php

declare(strict_types=1);

return [
    'inbox' => [
        'no_recipients' => [
            'title' => 'Formularios que no avisan a nadie',
            'found' => 'Un formulario activo no nombra ningún destinatario al que llegaría un correo: ninguno, solo administradores eliminados o desactivados desde entonces, o direcciones que no son direcciones.',
            'why' => 'Cada envío se guarda y se da las gracias al visitante, pero nadie se entera hasta que alguien abre la bandeja: una consulta puede esperar días.',
            'fix' => 'Abre el formulario, ve a la pestaña Avisos y añade un administrador o una dirección. Si el formulario solo se lee en el panel, ignora este aviso.',
        ],
    ],
];
