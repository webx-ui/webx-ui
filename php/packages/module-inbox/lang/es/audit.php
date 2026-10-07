<?php

declare(strict_types=1);

return [
    'no-recipients' => 'El formulario :form (:slug) no avisa a nadie',
    'notify-failed' => 'Notificaciones fallidas en los últimos :days días: :count',
    'notify-queued' => 'Notificaciones en cola desde hace más de :minutes minutos: :count',
    'captcha-keys' => 'El formulario :form (:slug) pide :provider y al .env del sitio le falta :missing',
    'captcha-unused' => 'El formulario :form (:slug) no tiene captcha y el sitio tiene claves para :providers',
    'spam-without-captcha' => 'El formulario :form (:slug) no tiene captcha y recibe spam. En :days días, marcados como spam: :spam, rechazados: :refused',
];
