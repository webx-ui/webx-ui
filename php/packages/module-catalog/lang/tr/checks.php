<?php

declare(strict_types=1);

return [
    'catalog' => [
        'category_description' => [
            'title' => 'Açıklaması olmayan kategoriler',
            'found' => 'Yayındaki kategorilerin açıklaması yok.',
            'why' => 'Yalnızca ürün listesinden oluşan bir kategori sayfası arama motoruna pek bir şey söylemez ve kolayca zayıf içerikli sayfa sayılır.',
            'fix' => 'Kategoride neler olduğunu ve içinden nasıl seçim yapılacağını birkaç cümleyle yazın.',
        ],
        'product_image' => [
            'title' => 'Görseli olmayan ürünler',
            'found' => 'Yayındaki ürünlerin görseli yok.',
            'why' => 'Görseli olmayan ürün daha az satar, listede yarım kalmış görünür ve paylaşıldığında gösterecek bir şeyi olmaz.',
            'fix' => 'Her ürüne en az bir görsel ekleyin ya da hazır olmayanları yayından kaldırın.',
        ],
        'product_price' => [
            'title' => 'Fiyatı olmayan ürünler',
            'found' => 'Yayındaki ürünlerin fiyatı yok ya da fiyatı sıfır.',
            'why' => 'Ziyaretçi ürünün ne kadar olduğunu anlayamaz ve ürün alışveriş sonuçlarında gösterilemez.',
            'fix' => 'Fiyatı girin ya da henüz satışta olmayan ürünleri yayından kaldırın.',
        ],
    ],
];
