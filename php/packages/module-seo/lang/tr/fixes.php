<?php

declare(strict_types=1);

return [
    'seo' => [
        'normalise-host' => [
            'title' => 'Ana aynaya yönlendir',
            'description' => 'SEO ayarlarında “Ana ayna” seçeneğini açar: diğer ad, bu adrese tek bir 301 ile yanıt verir.',
        ],
        'normalise-https' => [
            'title' => 'https’e yönlendir',
            'description' => 'SEO ayarlarında “Her zaman https” seçeneğini açar: http ile açılan adres, https’e tek bir 301 ile yanıt verir.',
        ],
        'normalise-slashes' => [
            'title' => 'Çift eğik çizgileri birleştir',
            'description' => 'SEO ayarlarında “Çift eğik çizgileri birleştir” seçeneğini açar.',
        ],
        'normalise-index' => [
            'title' => 'Index dosyalarını kes',
            'description' => 'SEO ayarlarında “Index dosyalarını kes” seçeneğini açar: /index.php ve /index.html klasöre yönlendirilir.',
        ],
        'normalise-trailing' => [
            'title' => 'Sondaki eğik çizginin tek biçimi',
            'description' => 'SEO ayarlarında “Sondaki eğik çizgi” seçeneğini sitenin kendi bağlantılarının kullandığı biçime ayarlar.',
        ],
        'normalise-case' => [
            'title' => 'Küçük harfe yönlendir',
            'description' => 'SEO ayarlarında “Küçük harf” seçeneğini açar: /About, /about adresine tek bir 301 ile yanıt verir.',
        ],
        'collapse-chain' => [
            'title' => 'Zinciri kısalt',
            'description' => 'Zincirdeki her tam yönlendirmeyi doğrudan zincirin bittiği adrese yöneltir.',
        ],
        'robots-sitemap' => [
            'title' => 'Sitemap satırını ekle',
            'description' => 'SEO ayarlarında robots.txt içine site haritasının adresiyle Sitemap: satırını yazar.',
        ],
    ],
];
