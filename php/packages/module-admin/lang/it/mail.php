<?php

declare(strict_types=1);

/*
 * Where the site's letters go, as the panel, the doctor and the agent say it
 * (`WebxUi\Admin\Mail\MailRoutes`).
 */

return [
    'trap-transport' => 'Le email vanno al mailer «:transport»: lì vengono registrate e nessuno le riceve.',
    'trap-host' => 'Le email vanno a :host, una casella di prova: fuori da essa nessuno le riceve.',
    'stale-config' => 'Le impostazioni in .env sono state cambiate dopo che la configurazione è stata messa in cache e non sono ancora in vigore. Ricostruisci la cache: php artisan webx:boot.',
    'queue-delay' => 'Le email partono tramite la coda: di solito entro un minuto, al prossimo giro della coda.',
    'queue-stalled' => 'In coda da più di :minutes minuti. La coda è in funzione?',
    'trapped' => 'Accettata da un trasporto di prova',
];
