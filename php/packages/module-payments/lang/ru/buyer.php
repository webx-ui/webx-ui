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
];
