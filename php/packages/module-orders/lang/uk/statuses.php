<?php

declare(strict_types=1);

return [
    'public' => [
        'accepted' => [
            'name' => 'Прийнято',
            'description' => 'Ми отримали замовлення і зв’яжемося з вами.',
        ],
        'in_progress' => [
            'name' => 'У роботі',
            'description' => 'Збираємо ваше замовлення.',
        ],
        'shipped' => [
            'name' => 'Відправлено',
            'description' => 'Замовлення в дорозі.',
        ],
        'completed' => [
            'name' => 'Виконано',
            'description' => 'Замовлення у вас. Дякуємо!',
        ],
        'cancelled' => [
            'name' => 'Скасовано',
            'description' => 'Замовлення скасовано.',
        ],
    ],
    'internal' => [
        'new' => 'Нове',
        'awaiting_payment' => 'Очікує оплати',
        'taken' => 'Взято в роботу',
        'awaiting_call' => 'Чекає дзвінка',
        'packing' => 'Збирається',
        'handed_to_delivery' => 'Передано в доставку',
        'completed' => 'Виконано',
        'cancelled' => 'Скасовано',
    ],
];
