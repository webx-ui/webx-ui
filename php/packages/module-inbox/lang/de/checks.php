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
        'captcha_unused' => [
            'title' => 'Formulare ohne Captcha auf einer Website mit Schlüsseln',
            'found' => 'Ein eingeschaltetes Formular verlangt kein Captcha, obwohl die Website Schlüssel für reCAPTCHA oder Turnstile hat.',
            'why' => 'Das versteckte Feld, der Zeitstempel und das Limit pro Adresse halten die meisten Roboter auf, daher ist das nur ein Hinweis: Das Captcha steht bereit für den Tag, an dem das Formular Spam bekommt.',
            'fix' => 'Bekommt das Formular Spam, das Captcha im Reiter „Spamschutz“ einschalten. Sonst diesen Befund ignorieren.',
        ],
        'spam_without_captcha' => [
            'title' => 'Formulare ohne Captcha, die Spam bekommen',
            'found' => 'Ein eingeschaltetes Formular ohne Captcha hat in den letzten Tagen als Spam markierte Einsendungen bekommen, oder der Spamschutz hat Einsendungen abgewiesen.',
            'why' => 'Roboter haben das Formular gefunden. Was durchkommt, landet im Posteingang und in den Benachrichtigungen, und die kostenlosen Schichten sind genau das, was sie zu umgehen versuchen.',
            'fix' => 'Im Reiter „Spamschutz“ des Formulars ein Captcha einschalten — die Website braucht seine Schlüssel in der .env — und das versteckte Feld eingeschaltet lassen. Ablehnungen werden pro Tag im Cache gezählt, nach dem Leeren des Caches also ab null.',
        ],
    ],
];
