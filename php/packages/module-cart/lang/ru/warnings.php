<?php

declare(strict_types=1);

// One phrase a code of Totals\Warning: `line.gone` is ['line']['gone'].
return [
    'line' => [
        'gone' => ':name больше нет в продаже. Его можно удалить.',
        'unavailable' => ':name сейчас нельзя купить.',
        'currency' => 'Цена :name в другой валюте, здесь его не купить.',
        'qty_limited' => 'Можно купить только :max шт. :name.',
    ],
    'price' => [
        'down' => ':name подешевел.',
        'up' => ':name подорожал.',
    ],
    'tenders' => [
        'capped' => 'Использована только часть баланса: он больше суммы.',
    ],
    'totals' => [
        'source_failed' => 'Часть суммы не удалось посчитать. Попробуйте позже.',
    ],
];
