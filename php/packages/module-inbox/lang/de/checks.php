<?php

declare(strict_types=1);

return [
    'inbox' => [
        'no_recipients' => [
            'title' => 'Formulare, die niemanden benachrichtigen',
            'found' => 'Ein aktives Formular nennt keinen Empfänger, den eine E-Mail erreichen würde: gar keinen, nur inzwischen gelöschte oder deaktivierte Administratoren oder Adressen, die keine sind.',
            'why' => 'Jede Einsendung wird gespeichert und der Besucher bekommt seinen Dank, aber niemand erfährt davon, bis jemand den Posteingang öffnet – eine Anfrage kann tagelang warten.',
            'fix' => 'Öffnen Sie das Formular, gehen Sie zum Tab „Benachrichtigungen“ und fügen Sie einen Administrator oder eine Adresse hinzu. Wird das Formular nur im Panel gelesen, ignorieren Sie diesen Befund.',
        ],
        'notification' => [
            'title' => 'Benachrichtigungen zu Einsendungen, die nicht verschickt wurden',
            'found' => 'Briefe zu Einsendungen sind fehlgeschlagen oder warten lange in der Warteschlange.',
            'why' => 'Die im Formular genannten Personen wissen nichts von Anfragen, die schon im Panel liegen, und die Website sagt es nicht.',
            'fix' => 'Fehlgeschlagen: Mail-Einstellungen korrigieren, den Queue-Worker neu starten (php artisan queue:restart), damit er sie liest, und die Benachrichtigung in der Einsendung erneut senden. Wartend: einen Queue-Worker starten.',
        ],
        'captcha_keys' => [
            'title' => 'Formulare mit einem Captcha, für das die Website keine Schlüssel hat',
            'found' => 'Ein eingeschaltetes Formular verlangt reCAPTCHA oder Turnstile, und in der .env der Website fehlen der Website-Schlüssel, das Secret oder beides.',
            'why' => 'Ohne Website-Schlüssel wird das Widget nicht gezeigt, ohne Secret lässt sich keine Antwort prüfen. So oder so lehnt das Formular jede Einsendung ab, und Besucher erreichen Sie nicht.',
            'fix' => 'WEBX_INBOX_RECAPTCHA_KEY und WEBX_INBOX_RECAPTCHA_SECRET (oder das TURNSTILE-Paar) in die .env der Website eintragen, WEBX_INBOX_RECAPTCHA_TYPE passend zur Art der Schlüssel, und den Konfigurations-Cache leeren (php artisan config:clear). Oder das Captcha im Reiter „Spamschutz“ des Formulars ausschalten.',
        ],
    ],
];
