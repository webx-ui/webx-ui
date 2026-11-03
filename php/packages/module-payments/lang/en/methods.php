<?php

declare(strict_types=1);

// The built-in payment methods (spec §12, §17): their default titles, notes and settings.
return [
    'on_receipt' => [
        'title' => 'Payment on receipt',
        'pickup' => 'Pay at the counter',
        'cod_note' => 'The delivery service charges its own fee for cash on delivery',
        'settings' => [
            'title_pickup' => 'Title at the shop\'s pick-up point',
            'cod_note' => 'Note on the cash-on-delivery fee',
        ],
    ],
    'bank_transfer' => [
        'title' => 'Bank transfer',
        'purpose' => 'Payment for order {number}',
        'settings' => [
            'recipient' => 'Recipient',
            'account' => 'Account and bank',
            'purpose' => 'Purpose of payment ({number} — the order\'s number)',
            'note' => 'Payment term',
        ],
    ],
];
