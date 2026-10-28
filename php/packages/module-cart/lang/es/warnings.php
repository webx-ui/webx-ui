<?php

declare(strict_types=1);

// One phrase a code of Totals\Warning: `line.gone` is ['line']['gone'].
return [
    'line' => [
        'gone' => ':name ya no está disponible. Puedes quitarlo.',
        'unavailable' => ':name no se puede comprar ahora.',
        'currency' => 'El precio de :name está en otra moneda y no se puede comprar aquí.',
        'qty_limited' => 'Solo se pueden comprar :max de :name.',
    ],
    'price' => [
        'down' => ':name ahora es más barato.',
        'up' => ':name ahora cuesta más.',
    ],
    'tenders' => [
        'capped' => 'Solo se usa parte de tu saldo: es mayor que el total.',
    ],
    'totals' => [
        'source_failed' => 'No se pudo calcular parte del total. Inténtalo más tarde.',
    ],
];
