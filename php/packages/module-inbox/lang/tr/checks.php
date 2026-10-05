<?php

declare(strict_types=1);

return [
    'inbox' => [
        'no_recipients' => [
            'title' => 'Kimseye haber vermeyen formlar',
            'found' => 'Açık bir form, e-postanın ulaşacağı hiçbir alıcı belirtmiyor: hiç alıcı yok, yalnızca o zamandan beri silinmiş ya da kapatılmış yöneticiler var veya adres olmayan adresler.',
            'why' => 'Her gönderim kaydedilir ve ziyaretçiye teşekkür edilir, ama biri gelen kutusunu açana kadar kimsenin haberi olmaz — bir talep günlerce bekleyebilir.',
            'fix' => 'Formu açın, Bildirimler sekmesine gidin ve bir yönetici ya da adres ekleyin. Form yalnızca panelde okunuyorsa bu bulguyu yok sayın.',
        ],
    ],
];
