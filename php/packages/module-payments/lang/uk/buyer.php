<?php

declare(strict_types=1);

// What the buyer reads (spec §10, §12, §17): the payment step, the bank details, the order's line.
return [
    'step' => [
        'methods' => 'Спосіб оплати',
        'save' => 'Зберегти',
        'delivery_first' => 'Оберіть доставку — від неї залежать способи оплати',
        'method_dropped' => 'Спосіб оплати недоступний для цієї доставки. Оберіть інший.',
    ],
    'errors' => [
        'choose_method' => 'Оберіть спосіб оплати',
        'method_gone' => 'Спосіб оплати більше недоступний, оберіть інший',
    ],
    'instructions' => [
        'title' => 'Реквізити для оплати',
        'recipient' => 'Отримувач',
        'account' => 'Рахунок',
        'purpose' => 'Призначення платежу',
        'note' => 'Термін',
    ],
    'status' => [
        'paid' => 'Оплачено',
        'awaiting' => 'Очікує оплати',
        'pay' => 'Оплатити',
    ],
    'pay' => [
        'title' => 'Оплата замовлення :number',
        'description' => 'Замовлення :number',
        'due' => 'До сплати: :amount',
        'paid' => 'Замовлення :number оплачено',
        'cancelled' => 'Замовлення :number скасовано',
        'checking' => 'Перевіряємо оплату. Зазвичай це займає до хвилини.',
        'failed' => 'Оплата не пройшла',
        'unavailable' => 'Не вдалося відкрити оплату. Спробуйте ще раз або оберіть інший спосіб.',
        'on_receipt' => 'Замовлення оплачується при отриманні',
        'order_link' => 'До замовлення',
        'redirecting' => 'Переходимо на сторінку оплати…',
        'go' => 'Перейти до оплати',
    ],
];
