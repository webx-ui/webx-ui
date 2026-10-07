<?php

declare(strict_types=1);

return [
    'no-recipients' => 'O formulário :form (:slug) não avisa ninguém',
    'notify-failed' => 'Notificações que falharam nos últimos :days dias: :count',
    'notify-queued' => 'Notificações na fila há mais de :minutes minutos: :count',
    'captcha-keys' => 'O formulário :form (:slug) pede :provider e falta no .env do site :missing',
    'captcha-unused' => 'O formulário :form (:slug) não tem captcha e o site tem chaves para :providers',
    'spam-without-captcha' => 'O formulário :form (:slug) não tem captcha e recebe spam. Em :days dias, marcados como spam: :spam, recusados: :refused',
];
