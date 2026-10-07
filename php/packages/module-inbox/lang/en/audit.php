<?php

declare(strict_types=1);

return [
    'no-recipients' => 'The form :form (:slug) tells nobody when it is sent',
    'notify-failed' => 'Notifications that failed in the last :days days: :count',
    'notify-queued' => 'Notifications waiting in the queue for over :minutes minutes: :count',
    'captcha-keys' => 'The form :form (:slug) asks for :provider, and the site’s .env lacks :missing',
    'captcha-unused' => 'The form :form (:slug) has no captcha, and the site has keys for :providers',
    'spam-without-captcha' => 'The form :form (:slug) has no captcha and draws spam. In :days days, marked as spam: :spam, refused: :refused',
];
