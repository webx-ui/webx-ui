<?php

declare(strict_types=1);

return [
    'public' => [
        'accepted' => [
            'name' => 'Alındı',
            'description' => 'Siparişiniz bize ulaştı, sizinle iletişime geçeceğiz.',
        ],
        'in_progress' => [
            'name' => 'Hazırlanıyor',
            'description' => 'Siparişinizi hazırlıyoruz.',
        ],
        'shipped' => [
            'name' => 'Kargoya verildi',
            'description' => 'Siparişiniz yolda.',
        ],
        'completed' => [
            'name' => 'Tamamlandı',
            'description' => 'Sipariş size ulaştı. Teşekkürler!',
        ],
        'cancelled' => [
            'name' => 'İptal edildi',
            'description' => 'Sipariş iptal edildi.',
        ],
    ],
    'internal' => [
        'new' => 'Yeni',
        'awaiting_payment' => 'Ödeme bekleniyor',
        'taken' => 'İşleme alındı',
        'awaiting_call' => 'Arama bekliyor',
        'packing' => 'Paketleniyor',
        'handed_to_delivery' => 'Kargoya teslim edildi',
        'completed' => 'Tamamlandı',
        'cancelled' => 'İptal edildi',
    ],
];
