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
];
