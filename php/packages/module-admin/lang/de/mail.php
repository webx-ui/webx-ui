<?php

declare(strict_types=1);

/*
 * Where the site's letters go, as the panel, the doctor and the agent say it
 * (`WebxUi\Admin\Mail\MailRoutes`).
 */

return [
    'trap-transport' => 'Briefe gehen an den Mailer „:transport“: Dort werden sie protokolliert, und niemand erhält sie.',
    'trap-host' => 'Briefe gehen an :host, ein Test-Postfach: Außerhalb davon erhält sie niemand.',
    'stale-config' => 'Die Einstellungen in .env wurden geändert, nachdem die Konfiguration zwischengespeichert wurde, und gelten noch nicht. Cache neu erstellen: php artisan webx:boot.',
    'queue-delay' => 'Briefe gehen über die Warteschlange: meist innerhalb einer Minute, sobald sie das nächste Mal läuft.',
    'queue-stalled' => 'Seit mehr als :minutes Minuten in der Warteschlange. Läuft die Warteschlange?',
    'trapped' => 'Von einem Testtransport angenommen',
];
