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
        'sender' => 'Gönderici',
        'contact' => 'İletişim kişisi',
        'sender_city' => 'Gönderim şehri',
        'sender_point' => 'Gönderim şubesi',
        'locker_width' => 'Kargo dolabı: varsayılan genişlik, cm',
        'locker_height' => 'Kargo dolabı: varsayılan yükseklik, cm',
        'locker_length' => 'Kargo dolabı: varsayılan uzunluk, cm',
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
    'waybill' => [
        'weight' => 'Ağırlık, kg',
        'seats' => 'Koli',
        'cargo_type' => 'Yük türü',
        'description' => 'Yük açıklaması',
        'date' => 'Gönderim tarihi',
        'width' => 'Genişlik, cm',
        'height' => 'Yükseklik, cm',
        'length' => 'Uzunluk, cm',
    ],
    'errors' => [
        'no_cod' => 'Kapıda ödeme bu kargo şirketinde henüz desteklenmiyor.',
        'name_empty' => 'Alıcının adını ve soyadını girin.',
        'name_letters' => 'Kargo şirketi adı yalnızca Ukrayna harfleriyle kabul eder.',
        'phone' => 'Kargo şirketi Ukrayna cep numarası kabul eder: +380 ve dokuz rakam.',
        'no_sender' => 'Önce kargo şirketinin ayarlarında göndericiyi, iletişim kişisini, gönderim şehrini ve şubesini seçin.',
        'contact_gone' => 'Ayarlardaki iletişim kişisi artık kargo şirketinin hesabında yok: başka birini seçin.',
        'no_town' => 'Kargo şirketi siparişin yerleşim yerini bilmiyor: yerleşim yerleri kuyruğunda bağlayın.',
        'no_point' => 'Siparişte kargo şirketinin şubesi veya dolabı yok.',
        'no_street' => 'Siparişte kargo şirketinin sokağı veya bina numarası yok.',
        'weight' => 'Ağırlık sıfırdan büyük olmalı.',
        'weight_too_much' => 'Ağırlık noktanın kabul ettiğinden fazla.',
        'seats' => 'Koli sayısı birden başlayan bir tam sayıdır.',
        'locker_seats' => 'Kargo dolabı tek koli kabul eder.',
        'cargo_type' => 'Listeden bir yük türü seçin.',
        'size' => 'Ölçüler sıfırdan büyük olmalı.',
        'locker_size' => 'Kargo dolabı en fazla 40 × 30 × 60 cm kabul eder.',
        'date' => 'Gönderim tarihi bugün veya üç ay içinde bir gün olmalı.',
        'read_only' => 'Bu site kargo şirketinin anahtarını yalnızca okuma için kullanıyor: irsaliyeler kapalı.',
        'no_ref' => 'Bu irsaliyenin kargo şirketindeki kimliği saklanmadı: kargo şirketinin hesabından iptal edin.',
        'carrier_said' => 'Kargo şirketi reddetti: :text',
    ],
    'remarks' => [
        'novaposhta_service_point' => 'Atlandı, kargo şirketinin hizmet noktaları: :count',
        'novaposhta_deny_to_select' => 'Atlandı, seçime kapalı: :count',
        'novaposhta_invalid_point' => 'Atlandı, eksik verili: :count',
    ],
];
