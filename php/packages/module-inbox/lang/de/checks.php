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
    ],
];
