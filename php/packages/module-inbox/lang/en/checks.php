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
    ],
];
