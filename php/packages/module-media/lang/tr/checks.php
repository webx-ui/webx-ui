<?php

declare(strict_types=1);

return [
    'media' => [
        'missing_file' => [
            'title' => 'Diskte eksik olan kitaplık dosyaları',
            'found' => 'Medya kitaplığı, diskte olmayan bir dosyayı listeliyor.',
            'why' => 'Onu kullanan her sayfa ve alan bozuk bir görsel ya da ölü bir indirme bağlantısı gösterir.',
            'fix' => 'Dosyayı medya kitaplığına yeniden yükleyin ya da storage klasörünü sitenin geldiği yerden kopyalayın.',
        ],
        'heavy' => [
            'title' => 'Bir sayfa için fazla ağır görseller',
            'found' => 'Medya kitaplığındaki görseller sınırdan daha ağır.',
            'why' => 'Böyle bir görseli gösteren sayfa telefonda yavaş yüklenir ve arama motorları yavaş sayfaları daha aşağı sıralar.',
            'fix' => 'Bunları daha küçük sürümlerle değiştirin: bir sayfa fotoğrafı nadiren 2000 pikselden geniş ya da birkaç yüz kilobayttan ağır olmalıdır.',
        ],
        'orphan_thumbs' => [
            'title' => 'Silinmiş dosyaların önizlemeleri',
            'found' => 'Diskte, medya kitaplığında artık olmayan dosyaların önizleme klasörleri duruyor.',
            'why' => 'Yer kaplıyorlar ve onları hiçbir şey göstermeyecek.',
            'fix' => 'Buradaki düğmeyle ya da php artisan webx:media:prune-thumbs komutuyla silin.',
        ],
    ],
];
