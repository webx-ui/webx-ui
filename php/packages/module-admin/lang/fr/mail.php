<?php

declare(strict_types=1);

/*
 * Where the site's letters go, as the panel, the doctor and the agent say it
 * (`WebxUi\Admin\Mail\MailRoutes`).
 */

return [
    'trap-transport' => 'Les e-mails partent vers le mailer « :transport » : ils y sont consignés et personne ne les reçoit.',
    'trap-host' => 'Les e-mails partent vers :host, une boîte de test : personne ne les reçoit en dehors.',
    'stale-config' => 'Les réglages de .env ont été modifiés après la mise en cache de la configuration et ne sont pas encore appliqués. Reconstruisez le cache : php artisan webx:boot.',
    'queue-delay' => 'Les e-mails partent par la file d’attente : en général en moins d’une minute, à son prochain passage.',
    'queue-stalled' => 'En file d’attente depuis plus de :minutes minutes. La file tourne-t-elle ?',
    'trapped' => 'Accepté par un transport de test',
];
