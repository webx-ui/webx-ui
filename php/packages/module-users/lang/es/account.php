<?php

declare(strict_types=1);

return [
    'menu' => [
        'label' => 'Mi cuenta',
        'profile' => 'Perfil',
        'security' => 'Acceso y seguridad',
        'sign_out' => 'Cerrar sesión',
    ],
    'profile' => [
        'title' => 'Perfil',
        'personal' => 'Datos personales',
        'save' => 'Guardar',
        'saved' => 'Guardado.',
    ],
    'email' => [
        'title' => 'Correo electrónico',
        'confirmed' => 'confirmado',
        'unconfirmed' => 'sin confirmar',
        'pending' => 'Pendiente de confirmación: :email — sigue el enlace del correo.',
        'submit' => 'Cambiar el correo',
    ],
    'security' => [
        'title' => 'Acceso y seguridad',
    ],
    'password' => [
        'title' => 'Contraseña',
        'current' => 'Contraseña actual',
        'new' => 'Nueva contraseña',
        'confirmation' => 'Repite la nueva contraseña',
        'submit' => 'Cambiar la contraseña',
        'changed' => 'La contraseña se ha cambiado. Se ha cerrado la sesión en los demás dispositivos.',
        'none' => 'Tu cuenta aún no tiene contraseña. Te enviaremos un enlace para crearla.',
        'set' => 'Crear una contraseña',
        'link_sent' => 'Hemos enviado un enlace a :email.',
    ],
    'sessions' => [
        'title' => 'Cerrar sesión en todas partes',
        'text' => 'Cierra las sesiones en todos los demás dispositivos y navegadores. Aquí seguirás conectado.',
        'submit' => 'Cerrar sesión en todas partes',
        'done' => 'Se ha cerrado la sesión en todos los demás dispositivos.',
    ],
    'delete' => [
        'title' => 'Eliminar la cuenta',
        'text' => 'La cuenta y todo lo que guarda se eliminan para siempre. No se puede recuperar.',
        'password' => 'Contraseña actual',
        'email' => 'Tu correo',
        'email_hint' => 'Escribe la dirección de la cuenta para confirmar.',
        'wrong_email' => 'Esta no es la dirección de la cuenta.',
        'submit' => 'Eliminar para siempre',
    ],
];
