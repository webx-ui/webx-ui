<?php

declare(strict_types=1);

return [
    'inbox' => [
        'no_recipients' => [
            'title' => 'Forms that tell nobody',
            'found' => 'A switched-on form names no recipient a letter would reach: none at all, or only administrators deleted or switched off since, or addresses that are not addresses.',
            'why' => 'Every submission is saved and the visitor is thanked, but nobody hears of it until somebody happens to open the inbox — an enquiry can wait for days.',
            'fix' => 'Open the form, go to the Notifications tab and add an administrator or an address. If the form is meant to be read only in the panel, ignore this issue.',
        ],
        'notification' => [
            'title' => 'Notifications about submissions that did not leave',
            'found' => 'Letters about submissions failed, or have waited in the queue for long.',
            'why' => 'The people the form names have not heard of enquiries the panel already holds, and nothing on the site says so.',
            'fix' => 'Failed: correct the mail settings, restart the queue worker (php artisan queue:restart) so it reads them, then send the notification again from the submission. Waiting: start a queue worker.',
        ],
    ],
];
