<?php

declare(strict_types=1);

// The built-in payment methods (spec §12, §17): their default titles, notes and settings.
return [
    'on_receipt' => [
        'title' => 'Оплата при получении',
        'pickup' => 'Оплата на кассе',
        'cod_note' => 'Комиссию за наложенный платёж берёт служба доставки по своим тарифам',
        'settings' => [
            'title_pickup' => 'Название при самовывозе из магазина',
            'cod_note' => 'Подпись о комиссии за наложенный платёж',
        ],
    ],
    'bank_transfer' => [
        'title' => 'По реквизитам',
        'purpose' => 'Оплата заказа {number}',
        'settings' => [
            'recipient' => 'Получатель',
            'account' => 'Счёт и банк',
            'purpose' => 'Назначение платежа ({number} — номер заказа)',
            'note' => 'Срок оплаты',
        ],
    ],
];
