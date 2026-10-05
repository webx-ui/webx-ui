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
    ],
];
