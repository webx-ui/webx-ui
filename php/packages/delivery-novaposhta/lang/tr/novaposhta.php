<?php

declare(strict_types=1);

return [
    'carrier' => [
        'title' => 'Nova Poshta',
    ],
    'types' => [
        'branch' => [
            'title' => 'Nova Poshta şubesi',
            'description' => 'Kargo şirketinin şubesinden teslim alın; gönderim ücretini teslim alırken kargo şirketine ödersiniz.',
        ],
        'locker' => [
            'title' => 'Nova Poshta kargo dolabı',
            'description' => 'Kargo şirketinin kargo dolabından teslim alın; gönderim ücretini teslim alırken kargo şirketine ödersiniz.',
        ],
        'courier' => [
            'title' => 'Nova Poshta kuryesi',
            'description' => 'Kargo şirketi paketi kapınıza getirir; gönderim ücretini teslim alırken kargo şirketine ödersiniz.',
        ],
    ],
    'settings' => [
        'payment_method' => 'Gönderim ödemesi',
        'payment_methods' => [
            'Cash' => 'Nakit',
            'NonCash' => 'Nakitsiz (kargo şirketiyle sözleşmeli)',
        ],
        'cargo_type' => 'Varsayılan yük türü',
        'cargo_types' => [
            'Parcel' => 'Koli',
            'Cargo' => 'Yük',
            'Documents' => 'Belgeler',
        ],
        'weight' => 'Varsayılan ağırlık, kg',
        'description' => 'Varsayılan yük açıklaması',
        'description_default' => 'Ürünler',
    ],
    'credentials' => [
        'api_key' => 'API anahtarı',
    ],
    'connection' => [
        'ok' => 'Hesaptaki gönderici sayısı: :count',
        'key_refused' => 'Kargo şirketi API anahtarını kabul etmedi.',
        'no_answer' => 'Kargo şirketi yanıt vermedi.',
        'no_sender' => 'Kargo şirketinin hesabında gönderici yok.',
        'no_key' => 'Önce API anahtarını girin.',
    ],
];
