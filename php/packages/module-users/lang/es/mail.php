<?php

declare(strict_types=1);

return [
    'hello' => '¡Hola, :name!',
    'ignore' => 'Si no lo has pedido tú, ignora este correo.',
    'verify' => [
        'subject' => 'Confirma tu e-mail en :site',
        'text' => 'Confirma que esta es tu dirección en :site.',
        'button' => 'Confirmar el e-mail',
        'expires' => 'El enlace funciona durante :hours horas.',
    ],
    'reset' => [
        'subject' => 'Una nueva contraseña en :site',
        'text' => 'Alguien ha pedido restablecer la contraseña de tu cuenta en :site.',
        'button' => 'Crear una nueva contraseña',
        'expires' => 'El enlace funciona durante :minutes minutos y una sola vez.',
    ],
    'password-changed' => [
        'subject' => 'Tu contraseña en :site ha cambiado',
        'text' => 'La contraseña de tu cuenta en :site se acaba de cambiar.',
        'not-you' => 'Si no has sido tú, restablece la contraseña ahora mismo.',
        'button' => 'Restablecer la contraseña',
    ],
    'email-change' => [
        'subject' => 'Confirma la nueva dirección en :site',
        'text' => 'Abre el enlace para iniciar sesión en :site con :email a partir de ahora.',
        'button' => 'Confirmar la dirección',
        'expires' => 'El enlace funciona durante :minutes minutos.',
    ],
    'email-changed' => [
        'subject' => 'Tu dirección de acceso en :site ha cambiado',
        'text' => 'Tu cuenta en :site ahora inicia sesión con :email.',
        'not-you' => 'Si no has sido tú, restablece la contraseña y contacta con nosotros.',
        'button' => 'Restablecer la contraseña',
    ],
];
