<?php

declare(strict_types=1);

return [
    'marks' => [
        'paid' => 'Paid',
        'received' => 'Received',
        'cancelled' => 'Cancelled',
        'refunded' => 'Refunded :amount',
    ],
    'blocks' => [
        'delivery' => 'Delivery',
        'payment' => 'Payment',
    ],
    'order' => [
        'title' => 'Order :number',
        'placed' => 'Placed on :date',
        'scale' => 'Where the order is',
        'awaiting_payment' => 'The order is waiting for payment.',
        'items' => 'Items',
        'subtotal' => 'Items',
        'total' => 'Total',
        'tenders' => 'Paid with balance and gift cards',
        'payable' => 'To pay',
        'contacts' => 'Contacts',
        'customer' => 'Buyer',
        'recipient' => 'Recipient',
        'comment' => 'Comment',
        'changed' => 'Items changed on :date',
        'due' => 'Left to pay: :amount',
        'over' => 'We will give you back :amount',
    ],
    'account' => [
        'menu' => 'My orders',
        'title' => 'My orders',
        'empty' => 'No orders yet. Orders you place while signed in will appear here.',
        'back' => '← All orders',
        'lines' => 'Items: :count',
        'pages' => 'Pages',
        'newer' => '← Newer',
        'older' => 'Older →',
        'deletion' => 'Orders in progress (:numbers) will be completed, but you will not see them in the account.',
    ],
];
