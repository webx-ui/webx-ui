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
];
