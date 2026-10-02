<?php

declare(strict_types=1);

return [
    'routing' => [
        'orphan' => [
            'title' => 'Silinmiş kayıtların adresleri',
            'found' => 'Adres kaydındaki bir satır, artık var olmayan bir kaydı gösteriyor.',
            'why' => 'Adres adını korurken 404 yanıtı verir; bu yüzden yeni bir kayıt onu kullanamaz.',
            'fix' => 'php artisan webx:routes:rebuild çalıştırın ya da kayıt yanlışlıkla silindiyse geri getirin.',
        ],
        'alias_broken' => [
            'title' => 'Hiçbir yere gitmeyen eski adresler',
            'found' => 'Bir takma ad — slug değiştikten sonra saklanan eski adres — hiçbir adrese ya da başka bir takma ada gidiyor.',
            'why' => 'Eski bağlantısı olan ziyaretçi 404 ya da yönlendirmeye yönlendirme alır.',
            'fix' => 'php artisan webx:routes:rebuild çalıştırın ya da takma adı SEO bölümünün «Otomatik» sekmesinden silin.',
        ],
        'shadowed' => [
            'title' => 'Uygulamanın kendisinin yanıt verdiği adresler',
            'found' => 'Uygulamanın bir rotası, adres kaydındaki bir kayıtla aynı adrese sahip.',
            'why' => 'Kayıt hiç gösterilmez: önce uygulamanın rotası yanıt verir.',
            'fix' => 'Kaydın slug’ını ya da uygulamanın rotasını değiştirin.',
        ],
        'no_address' => [
            'title' => 'Adresi olmayan kayıtlar',
            'found' => 'Bir dilde adresi olması gereken bir kaydın adresi yok.',
            'why' => 'Sayfa açılamaz, site haritasında yer almaz ve bağlantı verilemez.',
            'fix' => 'Kaydı yeniden kaydedin ya da php artisan webx:routes:rebuild çalıştırın.',
        ],
        'unknown_type' => [
            'title' => 'Yüklü olmayan bir modülün adresleri',
            'found' => 'Adres kaydında, yüklü hiçbir modülün tanımadığı bir türün adresleri var.',
            'why' => 'Hiçbir şeye yanıt vermezler ve yine de her yeni kayda karşı adlarını tutarlar.',
            'fix' => 'Bu satırları kaldırın ya da modülü yeniden yükleyin.',
        ],
    ],
];
