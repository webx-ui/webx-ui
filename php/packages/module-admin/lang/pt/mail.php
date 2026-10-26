<?php

declare(strict_types=1);

/*
 * Where the site's letters go, as the panel, the doctor and the agent say it
 * (`WebxUi\Admin\Mail\MailRoutes`).
 */

return [
    'trap-transport' => 'Os e-mails vão para o mailer «:transport»: lá são registados e ninguém os recebe.',
    'trap-host' => 'Os e-mails vão para :host, uma caixa de teste: fora dela ninguém os recebe.',
    'stale-config' => 'As definições em .env foram alteradas depois de a configuração ter sido guardada em cache e ainda não estão em vigor. Reconstrua a cache: php artisan webx:boot.',
    'queue-delay' => 'Os e-mails saem pela fila: normalmente em menos de um minuto, quando a fila correr.',
    'queue-stalled' => 'Na fila há mais de :minutes minutos. A fila está a funcionar?',
    'trapped' => 'Aceite por um transporte de teste',
];
