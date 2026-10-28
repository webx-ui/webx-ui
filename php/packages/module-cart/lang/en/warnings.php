<?php

declare(strict_types=1);

// One phrase a code of Totals\Warning: `line.gone` is ['line']['gone'].
return [
    'line' => [
        'gone' => ':name is no longer available. You can remove it.',
        'unavailable' => ':name cannot be bought now.',
        'currency' => ':name is priced in another currency and cannot be bought here.',
        'qty_limited' => 'Only :max of :name can be bought.',
    ],
    'price' => [
        'down' => ':name is now cheaper.',
        'up' => ':name now costs more.',
    ],
    'tenders' => [
        'capped' => 'Only part of your balance is used: it is more than the total.',
    ],
    'totals' => [
        'source_failed' => 'Part of the total could not be counted. Please try again later.',
    ],
];
