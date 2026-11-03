<?php

declare(strict_types=1);

// The built-in payment methods (spec §12, §17): their default titles, notes and settings.
return [
    'on_receipt' => [
        'title' => 'Оплата при отриманні',
        'pickup' => 'Оплата на касі',
        'cod_note' => 'Комісію за накладений платіж бере служба доставки за своїми тарифами',
        'settings' => [
            'title_pickup' => 'Назва при самовивозі з магазину',
            'cod_note' => 'Підпис про комісію за накладений платіж',
        ],
    ],
    'bank_transfer' => [
        'title' => 'За реквізитами',
        'purpose' => 'Оплата замовлення {number}',
        'settings' => [
            'recipient' => 'Отримувач',
            'account' => 'Рахунок і банк',
            'purpose' => 'Призначення платежу ({number} — номер замовлення)',
            'note' => 'Термін оплати',
        ],
    ],
];
