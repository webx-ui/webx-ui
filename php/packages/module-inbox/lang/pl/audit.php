<?php

declare(strict_types=1);

return [
    'no-recipients' => 'Formularz :form (:slug) nikogo nie powiadamia',
    'notify-failed' => 'Nieudane powiadomienia z ostatnich :days dni: :count',
    'notify-queued' => 'Powiadomienia w kolejce dłużej niż :minutes min: :count',
    'captcha-keys' => 'Formularz :form (:slug) wymaga :provider, a w .env witryny brakuje :missing',
    'captcha-unused' => 'Formularz :form (:slug) nie ma captchy, a witryna ma klucze do :providers',
    'spam-without-captcha' => 'Formularz :form (:slug) nie ma captchy i dostaje spam. W ciągu :days dni oznaczone jako spam: :spam, odrzucone: :refused',
];
