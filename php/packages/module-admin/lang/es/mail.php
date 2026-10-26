<?php

declare(strict_types=1);

/*
 * Where the site's letters go, as the panel, the doctor and the agent say it
 * (`WebxUi\Admin\Mail\MailRoutes`).
 */

return [
    'trap-transport' => 'Los correos van al mailer «:transport»: allí se registran y nadie los recibe.',
    'trap-host' => 'Los correos van a :host, un buzón de pruebas: fuera de él nadie los recibe.',
    'stale-config' => 'Los ajustes de .env se cambiaron después de guardar la configuración en caché y aún no se aplican. Reconstruye la caché: php artisan webx:boot.',
    'queue-delay' => 'Los correos salen por la cola: normalmente en menos de un minuto, cuando la cola se ejecute.',
    'queue-stalled' => 'En la cola desde hace más de :minutes minutos. ¿Funciona la cola?',
    'trapped' => 'Aceptado por un transporte de prueba',
];
