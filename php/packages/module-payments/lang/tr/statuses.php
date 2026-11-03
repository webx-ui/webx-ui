<?php

declare(strict_types=1);

// Statuses of attempts, their problems and where they started (spec §8.1, §9.4, §17).
return [
    'statuses' => [
        'created' => 'Oluşturuldu',
        'pending' => 'İşlemde',
        'authorized' => 'Bloke',
        'paid' => 'Ödendi',
        'failed' => 'Başarısız',
        'cancelled' => 'İptal edildi',
        'expired' => 'Süresi doldu',
    ],
    'problems' => [
        'duplicate' => 'İki kez ödendi',
        'amount_mismatch' => 'Yanlış tutar',
        'order_cancelled' => 'Sipariş iptal edildi',
        'hold_expiring' => 'Bloke süresi doluyor',
        'capture_failed' => 'Çekim başarısız',
    ],
    'sources' => [
        'checkout' => 'Ödeme adımı',
        'pay-page' => 'Ödeme sayfası',
        'poll' => 'Arka plan kontrolü',
        'panel' => 'Panel',
        'mcp' => 'Ajan',
        'notify' => 'Bildirim',
        'return' => 'Dönüş',
        'cli' => 'Konsol',
        'api' => 'API',
        'code' => 'Kod',
    ],
];
