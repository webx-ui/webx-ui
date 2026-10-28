<?php

declare(strict_types=1);

return [
    'batch' => 'Add from 1 to :max lines at a time.',
    'lines' => 'A cart holds at most :max lines.',
    'type' => 'This cannot go into the cart. It takes: :types.',
    'ref' => 'Say which item to add.',
    'unavailable' => 'This item cannot be bought now.',
    'currency' => 'The cart is not set up yet: the shop has no currency.',
    'currency_mismatch' => 'This item is priced in another currency.',
    'qty' => 'The quantity is from 1 to :max.',
    'options' => 'This item takes no options.',
    'key' => 'Each line of a batch needs its own key.',
    'parent' => 'A line can go only under a line of the same batch that is not under another one.',
    'gift_price' => 'A gift line costs at least 1.',
];
