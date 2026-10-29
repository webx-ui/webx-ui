<?php

declare(strict_types=1);

return [
    'no-recipients' => 'Das Formular :form (:slug) benachrichtigt niemanden',
    'notify-failed' => 'Fehlgeschlagene Benachrichtigungen der letzten :days Tage: :count',
    'notify-queued' => 'Benachrichtigungen seit über :minutes Minuten in der Warteschlange: :count',
    'captcha-keys' => 'Das Formular :form (:slug) verlangt :provider, und in der .env der Website fehlt :missing',
    'captcha-provider-mismatch' => 'Das Formular :form (:slug) nutzt :provider, und das Captcha der Website ist :site',
    'captcha-unused' => 'Das Formular :form (:slug) hat kein Captcha, und die Website hat Schlüssel für :providers',
    'spam-without-captcha' => 'Das Formular :form (:slug) hat kein Captcha und bekommt Spam. In :days Tagen als Spam markiert: :spam, abgelehnt: :refused',
    'legacy-views' => 'Das Anfrageformular wird von einer Ansicht aus der Zeit vor der Formular-Engine überschrieben: :path',
];
