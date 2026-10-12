<?php

declare(strict_types=1);

return [
    'no-recipients' => 'Il modulo :form (:slug) non avvisa nessuno',
    'notify-failed' => 'Notifiche non riuscite negli ultimi :days giorni: :count',
    'notify-queued' => 'Notifiche in coda da oltre :minutes minuti: :count',
    'captcha-keys' => 'Il modulo :form (:slug) chiede :provider e nel .env del sito manca :missing',
    'captcha-unused' => 'Il modulo :form (:slug) non ha captcha e il sito ha le chiavi per :providers',
    'spam-without-captcha' => 'Il modulo :form (:slug) non ha captcha e riceve spam. In :days giorni, segnati come spam: :spam, rifiutati: :refused',
    'legacy-views' => 'Il modulo delle richieste è sostituito da una vista precedente al motore dei moduli: :path',
];
