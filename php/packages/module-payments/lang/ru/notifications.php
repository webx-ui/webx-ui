<?php

declare(strict_types=1);

// The notices of payments (spec §11.5): the values it adds to the order's events and the manager's
// `payments.problem` with its default template; the site's settings tab that chooses its channels.
return [
    'problem' => 'Проблема оплаты',
    'vars' => [
        'payment_url' => 'Ссылка на оплату заказа',
        'payment_instructions' => 'Реквизиты для оплаты',
        'payment_method' => 'Способ оплаты',
        'number' => 'Номер заказа',
        'order_url' => 'Заказ в панели',
        'problem' => 'Что случилось',
        'amount' => 'Сумма',
        'method' => 'Способ оплаты',
        'gateway_ref' => 'ID платежа у шлюза',
        'message' => 'Подробности',
    ],
    'sample' => [
        'subject' => 'Проблема оплаты: заказ {{number}}',
        'template' => "{{problem}} — заказ {{number}}, {{amount}}, {{method}}.\n{{message}}\nID платежа: {{gateway_ref}}\n{{order_url}}",
    ],
    'settings' => [
        'tab' => 'Оплата',
        'card' => 'Уведомления',
        'help' => 'Кому сообщить, если деньги пришли не туда: двойная оплата, не та сумма, деньги за отменённый заказ, холд скоро истечёт, списание отклонено.',
    ],
];
