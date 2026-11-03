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
        'checking_long' => 'The bank has not confirmed the payment yet. If the money was taken, the order will be marked paid by itself and we will send an email. You may close this page.',
        'check_again' => 'Check again',
        'held' => 'Payment accepted. The amount is reserved on your card and will be charged by the shop.',
        'received' => 'Payment received',
        'accepted' => 'Payment accepted',
        'failed' => 'The payment did not go through',
        'unavailable' => 'Could not open the payment. Try again or choose another method.',
        'on_receipt' => 'The order is paid on receipt',
        'order_link' => 'Go to the order',
        'redirecting' => 'Taking you to the payment page…',
        'go' => 'Go to payment',
        'top_up' => 'A top-up of :amount is due',
        'top_up_button' => 'Top up',
        'online_now' => 'Pay online now',
        'wait' => 'If you have already paid in another tab, wait a minute before paying again.',
    ],
];
