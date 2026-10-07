<?php

declare(strict_types=1);

return [
    'no-recipients' => 'Das Formular :form (:slug) benachrichtigt niemanden',
    'notify-failed' => 'Fehlgeschlagene Benachrichtigungen der letzten :days Tage: :count',
    'notify-queued' => 'Benachrichtigungen seit über :minutes Minuten in der Warteschlange: :count',
    'captcha-keys' => 'Das Formular :form (:slug) verlangt :provider, und in der .env der Website fehlt :missing',
    'captcha-unused' => 'Das Formular :form (:slug) hat kein Captcha, und die Website hat Schlüssel für :providers',
    'spam-without-captcha' => 'Das Formular :form (:slug) hat kein Captcha und bekommt Spam. In :days Tagen als Spam markiert: :spam, abgelehnt: :refused',
];
