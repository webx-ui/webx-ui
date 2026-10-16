<?php

declare(strict_types=1);

return [
    'title' => 'Benachrichtigungen',
    'channel' => 'Kanal',
    'copy-name' => ':name (Kopie)',
    'unsaved' => 'Nicht gespeicherter Kanal',
    'permissions' => [
        'view' => 'Kanäle und das Zustellprotokoll sehen, samt dem, was Besucher über Formulare gesendet haben.',
        'manage' => 'Kanäle und ihre Geheimnisse einrichten, Tests senden, Chats suchen und fehlgeschlagene Benachrichtigungen erneut senden.',
    ],
    'statuses' => [
        'queued' => 'In der Warteschlange',
        'sent' => 'Gesendet',
        'failed' => 'Fehlgeschlagen',
        'skipped' => 'Übersprungen',
    ],
    'reasons' => [
        'channel_deleted' => 'Der Kanal ist gelöscht',
        'channel_disabled' => 'Der Kanal ist ausgeschaltet',
        'wrong_event' => 'Der Kanal ist für ein anderes Ereignis',
        'provider_missing' => 'Der Anbieter ist nicht installiert',
        'no_recipients' => 'Der Kanal hat keine Empfänger',
        'no_address' => 'Das Ereignis brachte keine Adresse',
        'admin_deleted' => 'Der Administrator ist gelöscht',
        'admin_inactive' => 'Der Administrator ist deaktiviert',
    ],
];
