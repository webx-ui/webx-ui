<?php

declare(strict_types=1);

return [
    'title' => 'Notifiche',
    'channel' => 'Canale',
    'copy-name' => ':name (copia)',
    'unsaved' => 'Canale non salvato',
    'permissions' => [
        'view' => 'Vedere i canali e il registro degli invii, compreso ciò che i visitatori hanno inviato tramite i moduli.',
        'manage' => 'Configurare i canali e i loro segreti, inviare test, cercare chat e reinviare le notifiche fallite.',
    ],
    'statuses' => [
        'queued' => 'In coda',
        'sent' => 'Inviato',
        'trapped' => 'Trasporto di prova',
        'failed' => 'Fallito',
        'skipped' => 'Saltato',
    ],
    'reasons' => [
        'channel_deleted' => 'Il canale è eliminato',
        'channel_disabled' => 'Il canale è disattivato',
        'wrong_event' => 'Il canale è per un altro evento',
        'provider_missing' => 'Il provider non è installato',
        'no_recipients' => 'Il canale non ha destinatari',
        'no_address' => 'L\'evento non ha portato alcun indirizzo',
        'admin_deleted' => 'L\'amministratore è eliminato',
        'admin_inactive' => 'L\'amministratore è disattivato',
    ],
];
