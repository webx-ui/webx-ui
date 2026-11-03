<?php

declare(strict_types=1);

// The notices of payments (spec §11.5): the values it adds to the order's events and the manager's
// `payments.problem` with its default template; the site's settings tab that chooses its channels.
return [
    'problem' => 'Проблема оплати',
    'vars' => [
        'payment_url' => 'Посилання на оплату замовлення',
        'payment_instructions' => 'Реквізити для оплати',
        'payment_method' => 'Спосіб оплати',
        'number' => 'Номер замовлення',
        'order_url' => 'Замовлення в панелі',
        'problem' => 'Що сталося',
        'amount' => 'Сума',
        'method' => 'Спосіб оплати',
        'gateway_ref' => 'ID платежу у шлюзі',
        'message' => 'Подробиці',
    ],
    'sample' => [
        'subject' => 'Проблема оплати: замовлення {{number}}',
        'template' => "{{problem}} — замовлення {{number}}, {{amount}}, {{method}}.\n{{message}}\nID платежу: {{gateway_ref}}\n{{order_url}}",
    ],
    'settings' => [
        'tab' => 'Оплата',
        'card' => 'Сповіщення',
        'help' => 'Кому повідомити, якщо гроші надійшли не туди: подвійна оплата, не та сума, гроші за скасоване замовлення, холд скоро спливе, списання відхилено.',
    ],
];
