<?php

declare(strict_types=1);

return [
    'no-recipients' => 'Le formulaire :form (:slug) ne prévient personne',
    'notify-failed' => 'Notifications échouées ces :days derniers jours : :count',
    'notify-queued' => 'Notifications en file depuis plus de :minutes minutes : :count',
    'captcha-keys' => 'Le formulaire :form (:slug) demande :provider, et il manque au .env du site :missing',
    'captcha-unused' => 'Le formulaire :form (:slug) n’a pas de captcha, et le site a des clés pour :providers',
    'spam-without-captcha' => 'Le formulaire :form (:slug) n’a pas de captcha et reçoit du spam. En :days jours, marqués comme spam : :spam, refusés : :refused',
];
