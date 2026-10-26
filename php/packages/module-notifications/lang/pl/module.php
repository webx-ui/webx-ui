<?php

declare(strict_types=1);

return [
    'title' => 'Powiadomienia',
    'channel' => 'Kanał',
    'copy-name' => ':name (kopia)',
    'unsaved' => 'Niezapisany kanał',
    'permissions' => [
        'view' => 'Widzieć kanały i dziennik wysyłek wraz z tym, co odwiedzający przesłali przez formularze.',
        'manage' => 'Konfigurować kanały i ich sekrety, wysyłać testy, wyszukiwać czaty i ponawiać nieudane powiadomienia.',
    ],
    'statuses' => [
        'queued' => 'W kolejce',
        'sent' => 'Wysłano',
        'trapped' => 'Transport testowy',
        'failed' => 'Błąd',
        'skipped' => 'Pominięto',
    ],
    'reasons' => [
        'channel_deleted' => 'Kanał został usunięty',
        'channel_disabled' => 'Kanał jest wyłączony',
        'wrong_event' => 'Kanał jest dla innego zdarzenia',
        'provider_missing' => 'Dostawca nie jest zainstalowany',
        'no_recipients' => 'Kanał nie ma odbiorców',
        'no_address' => 'Zdarzenie nie przyniosło adresu',
        'admin_deleted' => 'Administrator został usunięty',
        'admin_inactive' => 'Administrator jest wyłączony',
    ],
];
