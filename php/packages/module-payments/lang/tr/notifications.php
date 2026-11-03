<?php

declare(strict_types=1);

// The notices of payments (spec §11.5): the values it adds to the order's events and the manager's
// `payments.problem` with its default template; the site's settings tab that chooses its channels.
return [
    'problem' => 'Ödeme sorunu',
    'vars' => [
        'payment_url' => 'Sipariş ödeme bağlantısı',
        'payment_instructions' => 'Ödeme için banka bilgileri',
        'payment_method' => 'Ödeme yöntemi',
        'number' => 'Sipariş numarası',
        'order_url' => 'Paneldeki sipariş',
        'problem' => 'Ne oldu',
        'amount' => 'Tutar',
        'method' => 'Ödeme yöntemi',
        'gateway_ref' => 'Ödeme sağlayıcısındaki ödeme kimliği',
        'message' => 'Ayrıntılar',
    ],
    'sample' => [
        'subject' => 'Ödeme sorunu: sipariş {{number}}',
        'template' => "{{problem}} — sipariş {{number}}, {{amount}}, {{method}}.\n{{message}}\nÖdeme kimliği: {{gateway_ref}}\n{{order_url}}",
    ],
    'settings' => [
        'tab' => 'Ödemeler',
        'card' => 'Bildirimler',
        'help' => 'Para olmaması gereken yere geldiğinde kime haber verilir: iki kez ödendi, yanlış tutar, iptal edilmiş sipariş için para, süresi dolmak üzere olan blokaj, reddedilen tahsilat.',
    ],
];
