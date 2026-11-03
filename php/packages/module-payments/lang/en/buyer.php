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
    'pay' => [
        'title' => 'Payment for order :number',
        'description' => 'Order :number',
        'due' => 'To pay: :amount',
        'paid' => 'Order :number is paid',
        'cancelled' => 'Order :number is cancelled',
        'checking' => 'We are checking the payment. It usually takes up to a minute.',
        'failed' => 'The payment did not go through',
        'unavailable' => 'Could not open the payment. Try again or choose another method.',
        'on_receipt' => 'The order is paid on receipt',
        'order_link' => 'Go to the order',
        'redirecting' => 'Taking you to the payment page…',
        'go' => 'Go to payment',
    ],
];
