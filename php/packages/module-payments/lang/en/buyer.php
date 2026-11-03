<?php

declare(strict_types=1);

// What the buyer reads (spec §10, §12, §17): the payment step, the bank details, the order's line.
return [
    'step' => [
        'methods' => 'Payment method',
        'save' => 'Save',
        'delivery_first' => 'Choose delivery first — payment options depend on it',
        'method_dropped' => 'The payment method you chose is not available for this delivery. Choose another one.',
    ],
    'errors' => [
        'choose_method' => 'Choose a payment method',
        'method_gone' => 'The payment method is no longer available, choose another one',
    ],
    'instructions' => [
        'title' => 'Bank details for payment',
        'recipient' => 'Recipient',
        'account' => 'Account',
        'purpose' => 'Purpose of payment',
        'note' => 'Term',
    ],
    'status' => [
        'paid' => 'Paid',
        'awaiting' => 'Awaiting payment',
        'pay' => 'Pay',
    ],
];
