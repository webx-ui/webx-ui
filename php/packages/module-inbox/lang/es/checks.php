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
        'notification' => [
            'title' => 'Notificaciones de envíos que no salieron',
            'found' => 'Las cartas sobre envíos fallaron o llevan mucho tiempo en la cola.',
            'why' => 'Las personas que indica el formulario no saben de solicitudes que el panel ya tiene, y el sitio no lo dice.',
            'fix' => 'Fallidas: corrija la configuración del correo, reinicie el worker de la cola (php artisan queue:restart) para que la lea y envíe la notificación de nuevo desde el envío. En espera: inicie un worker de la cola.',
        ],
    ],
];
