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
        'notification' => [
            'title' => 'Gönderilemeyen başvuru bildirimleri',
            'found' => 'Başvurularla ilgili mektuplar gönderilemedi ya da uzun süredir kuyrukta bekliyor.',
            'why' => 'Formda belirtilen kişiler panelde zaten bulunan taleplerden habersiz ve site bunu söylemiyor.',
            'fix' => 'Gönderilemeyenler: e-posta ayarlarını düzeltin, kuyruk işçisini yeniden başlatın (php artisan queue:restart) ki ayarları okusun, sonra bildirimi başvurudan yeniden gönderin. Bekleyenler: bir kuyruk işçisi başlatın.',
        ],
    ],
];
