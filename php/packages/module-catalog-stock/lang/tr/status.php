<?php

declare(strict_types=1);

return [
    'title' => 'Ad',
    'code' => 'Kod',
    'code-help' => 'Latin harfleri, rakamlar ve kısa çizgiler: filtre adresindeki (stock_in-stock) ve şablonlardaki durum. Boş bırakılırsa addan oluşturulur.',
    'color' => 'Ton',
    'color-help' => 'Site durum satırını bu tonda boyar; panel aynı tonda bir etiket gösterir.',
    'tones' => [
        'neutral' => 'Nötr',
        'primary' => 'Birincil',
        'success' => 'Başarı',
        'warning' => 'Uyarı',
        'danger' => 'Tehlike',
        'info' => 'Bilgi',
    ],
    'purchasable' => 'Satın alınabilir',
    'purchasable-help' => 'Kapalıysa bu durumdaki ürün satın alınamaz ve site satın al düğmesi yerine durumun adını gösterir.',
    'default' => 'Varsayılan',
    'default-help' => 'Durumu belirlenmemiş her ürünün ve yeni ürünün durumu. Değiştirmek için başka bir durumu varsayılan yapın.',
    'filterable' => 'Filtrede',
    'filterable-help' => 'Kapalıysa durum katalog filtresinde bir seçenek olmaz.',
    'new' => 'Yeni durum',
    'empty' => 'Henüz stok durumu yok.',
    'empty-help' => 'Durum, ürünün stokta olup olmadığını ve satın alınıp alınamayacağını söyler.',
    'order' => 'Buradaki sıra filtrenin sırasıdır',
    'hidden' => 'Filtrede değil',
    'count' => 'Ürünler: :count',
    'show-items' => 'Ürünlerini göster',
    'delete-blocked' => 'İçinde ürün varken silinemez — önce ürünleri başka bir duruma taşıyın.',
    'delete-text' => 'Çöp kutusuna gider ve filtreden çıkar.',
    'deleted' => 'Durum çöp kutusunda.',
    'saved' => 'Durum kaydedildi.',
];
