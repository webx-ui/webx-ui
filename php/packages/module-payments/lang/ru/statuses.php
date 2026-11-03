<?php

declare(strict_types=1);

// Statuses of attempts, their problems and where they started (spec §8.1, §9.4, §17).
return [
    'statuses' => [
        'created' => 'Создана',
        'pending' => 'В обработке',
        'authorized' => 'Заблокирована',
        'paid' => 'Оплачена',
        'failed' => 'Не прошла',
        'cancelled' => 'Отменена',
        'expired' => 'Истекла',
    ],
    'problems' => [
        'duplicate' => 'Оплачено дважды',
        'amount_mismatch' => 'Не та сумма',
        'order_cancelled' => 'Заказ отменён',
        'hold_expiring' => 'Холд истекает',
        'capture_failed' => 'Списание не прошло',
    ],
    'sources' => [
        'checkout' => 'Оформление',
        'pay-page' => 'Страница оплаты',
        'poll' => 'Фоновая проверка',
        'panel' => 'Панель',
        'mcp' => 'Агент',
        'notify' => 'Уведомление',
        'return' => 'Возврат',
        'cli' => 'Консоль',
        'api' => 'API',
        'code' => 'Код',
    ],
];
