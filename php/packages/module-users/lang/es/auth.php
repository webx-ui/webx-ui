<?php

declare(strict_types=1);

return [
    'sign_in' => [
        'title' => 'Iniciar sesión',
        'remember' => 'Recordarme',
        'submit' => 'Entrar',
        'no_account' => '¿Aún no tienes cuenta?',
        'register' => 'Regístrate',
    ],
    'register' => [
        'title' => 'Crear una cuenta',
        'submit' => 'Registrarse',
        'password_hint' => 'Al menos :min caracteres.',
        'have_account' => '¿Ya tienes cuenta?',
        'sign_in' => 'Inicia sesión',
    ],
    'fields' => [
        'email' => 'Correo electrónico',
        'password' => 'Contraseña',
        'first_name' => 'Nombre',
        'last_name' => 'Apellidos',
        'phone' => 'Teléfono',
    ],
    'failed' => 'Correo electrónico o contraseña incorrectos.',
    'blocked' => 'Esta cuenta está bloqueada. Ponte en contacto con la tienda.',
    'taken' => 'Esta dirección ya está registrada: inicia sesión o recupera tu contraseña.',
    'throttled' => 'Demasiados intentos. Espera :seconds s.',
    'forgot' => [
        'link' => '¿Olvidaste tu contraseña?',
        'title' => 'Restablecer la contraseña',
        'intro' => 'Introduce el e-mail de tu cuenta: te enviaremos un enlace para una contraseña nueva.',
        'submit' => 'Enviar el enlace',
        'sent' => 'Si existe una cuenta con esta dirección, le hemos enviado un enlace.',
        'back' => 'Volver a iniciar sesión',
    ],
    'reset' => [
        'title' => 'Nueva contraseña',
        'password' => 'Nueva contraseña',
        'confirmation' => 'Repite la contraseña',
        'submit' => 'Guardar la contraseña',
        'expired' => 'El enlace ha caducado. Pide uno nuevo abajo.',
    ],
    'verify' => [
        'banner' => 'Confirma tu e-mail: hemos enviado un correo a',
        'resend' => 'Enviar de nuevo',
        'sent_short' => 'Enviado',
        'close' => 'Cerrar',
        'sent' => 'Hemos enviado el correo a :email otra vez.',
        'already' => 'Tu e-mail ya está confirmado.',
        'throttled' => 'El correo ya se ha enviado. Inténtalo de nuevo en :minutes min.',
        'sign_in' => 'Iniciar sesión',
        'continue' => 'Continuar',
        'confirmed' => [
            'title' => 'E-mail confirmado',
            'text' => 'Gracias: tu dirección está confirmada.',
        ],
        'expired' => [
            'title' => 'El enlace ha caducado',
            'text' => 'Este enlace ya no funciona. Inicia sesión para recibir un correo nuevo.',
        ],
        'unverified' => [
            'title' => 'Confirma tu e-mail',
            'text' => 'Esta página se abre cuando confirmes tu e-mail. Hemos enviado un correo a :email.',
        ],
    ],
    'email' => [
        'new' => 'Nuevo e-mail',
        'password' => 'Contraseña actual',
        'wrong_password' => 'La contraseña no es correcta.',
        'same' => 'Ya inicias sesión con esta dirección.',
        'taken' => 'Esta dirección pertenece a otra cuenta.',
        'sent' => 'Hemos enviado un enlace a :email. Tu dirección cambiará cuando lo abras.',
        'changed' => [
            'title' => 'Dirección cambiada',
            'text' => 'Ahora inicias sesión con :email.',
        ],
    ],
];
