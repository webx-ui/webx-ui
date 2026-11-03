<?php

declare(strict_types=1);

// Refusals of the service, the API and the agent's tools (spec §17).
return [
    'mark' => [
        'paid' => 'Sipariş zaten ödendi',
        'cancelled' => 'Sipariş iptal edildi',
    ],
    'begin' => [
        'cancelled' => 'Sipariş iptal edildi',
        'nothing_due' => 'Sipariş için ödenecek bir şey yok',
        'not_online' => 'Yöntem çevrim içi değil',
    ],
];
