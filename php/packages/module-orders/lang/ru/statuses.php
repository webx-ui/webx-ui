<?php

declare(strict_types=1);

return [
    'public' => [
        'accepted' => [
            'name' => 'Принят',
            'description' => 'Мы получили заказ и свяжемся с вами.',
        ],
        'in_progress' => [
            'name' => 'В работе',
            'description' => 'Собираем ваш заказ.',
        ],
        'shipped' => [
            'name' => 'Отправлен',
            'description' => 'Заказ в пути.',
        ],
        'completed' => [
            'name' => 'Выполнен',
            'description' => 'Заказ у вас. Спасибо!',
        ],
        'cancelled' => [
            'name' => 'Отменён',
            'description' => 'Заказ отменён.',
        ],
    ],
    'internal' => [
        'new' => 'Новый',
        'awaiting_payment' => 'Ожидает оплаты',
        'taken' => 'Взят в работу',
        'awaiting_call' => 'Ждёт звонка',
        'packing' => 'Собирается',
        'handed_to_delivery' => 'Передан в доставку',
        'completed' => 'Выполнен',
        'cancelled' => 'Отменён',
    ],
];
