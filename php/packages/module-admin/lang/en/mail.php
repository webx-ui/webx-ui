<?php

declare(strict_types=1);

/*
 * Where the site's letters go, as the panel, the doctor and the agent say it
 * (`WebxUi\Admin\Mail\MailRoutes`).
 */

return [
    'trap-transport' => 'Letters go to the “:transport” mailer: they are written down there and nobody receives them.',
    'trap-host' => 'Letters go to :host, a test mailbox: nobody outside it receives them.',
    'stale-config' => 'The settings in .env were changed after the config was cached and are not in effect yet. Rebuild the cache: php artisan webx:boot.',
    'queue-delay' => 'Letters leave through the queue: usually within a minute, when the queue next runs.',
    'queue-stalled' => 'Waiting in the queue for more than :minutes minutes. Is the queue running?',
    'trapped' => 'Accepted by a test transport',
];
