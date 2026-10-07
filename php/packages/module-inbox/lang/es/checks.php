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
        'captcha_keys' => [
            'title' => 'Formularios que piden un captcha sin claves en el sitio',
            'found' => 'Un formulario activado pide reCAPTCHA o Turnstile, y al .env del sitio le falta la clave del sitio, el secreto o ambos.',
            'why' => 'Sin clave del sitio el widget no se dibuja; sin secreto no se puede verificar ninguna respuesta. En ambos casos el formulario rechaza cada envío y los visitantes no pueden contactarle.',
            'fix' => 'Añada WEBX_INBOX_RECAPTCHA_KEY y WEBX_INBOX_RECAPTCHA_SECRET (o el par TURNSTILE) al .env del sitio, con WEBX_INBOX_RECAPTCHA_TYPE según el tipo de clave, y limpie la caché de configuración (php artisan config:clear). O desactive el captcha en la pestaña Antispam del formulario.',
        ],
        'captcha_unused' => [
            'title' => 'Formularios sin captcha en un sitio que tiene las claves',
            'found' => 'Un formulario activado no pide captcha, aunque el sitio tiene claves para reCAPTCHA o Turnstile.',
            'why' => 'El campo oculto, la marca de tiempo y el límite por dirección detienen a la mayoría de los robots, así que esto es solo un aviso: el captcha está listo para el día en que el formulario reciba spam.',
            'fix' => 'Si el formulario recibe spam, active el captcha en su pestaña Antispam. Si no, ignore este problema.',
        ],
        'spam_without_captcha' => [
            'title' => 'Formularios sin captcha que reciben spam',
            'found' => 'Un formulario activado sin captcha recibió en los últimos días envíos marcados como spam, o el antispam rechazó envíos a él.',
            'why' => 'Los robots han encontrado el formulario. Lo que pasa llega a la bandeja y a las notificaciones, y las capas gratuitas son justo lo que intentan sortear.',
            'fix' => 'Active un captcha en la pestaña Antispam del formulario —el sitio necesita sus claves en .env— y mantenga el campo oculto. Los rechazos se cuentan por día en la caché, así que al vaciarla empiezan de cero.',
        ],
    ],
];
