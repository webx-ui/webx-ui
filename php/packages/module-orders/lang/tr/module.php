<?php

declare(strict_types=1);

return [
    'orders' => 'Siparişler',
    'description' => 'Sitede verilen siparişler: ne alındı, kim aldı ve her biri ne durumda.',
    'empty' => 'Henüz sipariş yok.',
    'empty-help' => 'Sitede bir sipariş verildiği anda burada görünür.',
    'order' => 'Sipariş',
    'statuses-cache' => 'Sipariş durumları',
    'order-field' => [
        'title' => 'Sipariş alanı',
        'cache' => 'Sipariş alanları',
        'key' => 'Anahtar',
        'type' => 'Tür',
        'place' => 'Yer',
        'label' => 'Ad',
        'hint' => 'İpucu',
        'options' => 'Seçenekler',
        'required' => 'Zorunlu',
        'active' => 'Etkin',
        'parent_id' => 'İşaretlenince göster',
    ],
    'fields' => [
        'customer_first_name' => 'Ad',
        'customer_last_name' => 'Soyad',
        'customer_phone' => 'Telefon',
        'customer_email' => 'E-posta',
        'recipient' => 'Alıcı',
        'comment' => 'Müşteri yorumu',
        'manager_note' => 'Yönetici notu',
        'user_id' => 'Ziyaretçi',
        'fields' => 'Ek alanlar',
    ],
    'milestones' => [
        'placed' => 'Verildi',
        'awaiting_payment' => 'Ödeme bekleniyor',
        'paid' => 'Ödendi',
        'received' => 'Teslim alındı',
        'cancelled' => 'İptal edildi',
        'refunded' => 'İade edildi',
    ],
    'colors' => [
        'neutral' => 'Nötr',
        'info' => 'Bilgi',
        'success' => 'Başarı',
        'warning' => 'Uyarı',
        'danger' => 'Tehlike',
        'accent' => 'Vurgu',
    ],
];
