<?php

declare(strict_types=1);

return [
    'title' => 'Bildirimler',
    'channel' => 'Kanal',
    'copy-name' => ':name (kopya)',
    'unsaved' => 'Kaydedilmemiş kanal',
    'permissions' => [
        'view' => 'Kanalları ve gönderim günlüğünü, ziyaretçilerin formlarla gönderdikleriyle birlikte görmek.',
        'manage' => 'Kanalları ve gizli bilgilerini ayarlamak, test göndermek, sohbet aramak ve başarısız bildirimleri yeniden göndermek.',
    ],
    'statuses' => [
        'queued' => 'Kuyrukta',
        'sent' => 'Gönderildi',
        'trapped' => 'Test aktarımı',
        'failed' => 'Başarısız',
        'skipped' => 'Atlandı',
    ],
    'reasons' => [
        'channel_deleted' => 'Kanal silinmiş',
        'channel_disabled' => 'Kanal kapalı',
        'wrong_event' => 'Kanal başka bir olay için',
        'provider_missing' => 'Sağlayıcı kurulu değil',
        'no_recipients' => 'Kanalın alıcısı yok',
        'no_address' => 'Olay bir adres getirmedi',
        'admin_deleted' => 'Yönetici silinmiş',
        'admin_inactive' => 'Yönetici devre dışı',
    ],
];
