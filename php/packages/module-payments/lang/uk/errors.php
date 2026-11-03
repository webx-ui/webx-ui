<?php

declare(strict_types=1);

// Refusals of the service, the API and the agent's tools (spec §17).
return [
    'mark' => [
        'paid' => 'Замовлення вже оплачено',
        'cancelled' => 'Замовлення скасовано',
    ],
    'begin' => [
        'cancelled' => 'Замовлення скасовано',
        'nothing_due' => 'За замовленням нічого сплачувати',
        'not_online' => 'Спосіб не онлайн',
    ],
];
