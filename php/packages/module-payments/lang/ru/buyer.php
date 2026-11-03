<?php

declare(strict_types=1);

// What the buyer reads (spec §10, §12, §17): the payment step, the bank details, the order's line.
return [
    'step' => [
        'methods' => 'Способ оплаты',
        'save' => 'Сохранить',
        'delivery_first' => 'Выберите доставку — от неё зависят способы оплаты',
        'method_dropped' => 'Способ оплаты недоступен для этой доставки. Выберите другой.',
    ],
    'errors' => [
        'choose_method' => 'Выберите способ оплаты',
        'method_gone' => 'Способ оплаты больше недоступен, выберите другой',
    ],
    'instructions' => [
        'title' => 'Реквизиты для оплаты',
        'recipient' => 'Получатель',
        'account' => 'Счёт',
        'purpose' => 'Назначение платежа',
        'note' => 'Срок',
    ],
    'status' => [
        'paid' => 'Оплачен',
        'awaiting' => 'Ждёт оплаты',
        'pay' => 'Оплатить',
    ],
    'pay' => [
        'title' => 'Оплата заказа :number',
        'description' => 'Заказ :number',
        'due' => 'К оплате: :amount',
        'paid' => 'Заказ :number оплачен',
        'cancelled' => 'Заказ :number отменён',
        'checking' => 'Проверяем оплату. Обычно это занимает до минуты.',
        'failed' => 'Оплата не прошла',
        'unavailable' => 'Не удалось открыть оплату. Попробуйте ещё раз или выберите другой способ.',
        'on_receipt' => 'Заказ оплачивается при получении',
        'order_link' => 'К заказу',
        'redirecting' => 'Переходим на страницу оплаты…',
        'go' => 'Перейти к оплате',
    ],
];
