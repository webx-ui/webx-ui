<?php

declare(strict_types=1);

/*
 * Where the site's letters go, as the panel, the doctor and the agent say it
 * (`WebxUi\Admin\Mail\MailRoutes`).
 */

return [
    'trap-transport' => 'Listy trafiają do nadawcy „:transport”: są tam zapisywane i nikt ich nie otrzymuje.',
    'trap-host' => 'Listy trafiają na :host, testową skrzynkę: poza nią nikt ich nie otrzymuje.',
    'stale-config' => 'Ustawienia w .env zmieniono po zapisaniu konfiguracji w pamięci podręcznej i jeszcze nie obowiązują. Odbuduj pamięć podręczną: php artisan webx:boot.',
    'queue-delay' => 'Listy wychodzą przez kolejkę: zwykle w ciągu minuty, gdy kolejka się wykona.',
    'queue-stalled' => 'W kolejce dłużej niż :minutes min. Czy kolejka działa?',
    'trapped' => 'Przyjęte przez transport testowy',
];
