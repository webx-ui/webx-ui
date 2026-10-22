<?php

declare(strict_types=1);

return [
    'actions' => [
        'publish' => 'Yayımla',
        'unpublish' => 'Yayından kaldır',
        'set-category' => 'Ana kategoriyi belirle',
        'add-category' => 'Kategori ekle',
        'remove-category' => 'Kategoriden çıkar',
        'delete' => 'Sil',
        'restore' => 'Geri getir',
    ],
    'params' => [
        'category' => 'Kategori',
    ],
    'errors' => [
        'selection' => 'Ürünleri seçin: kimlik listesi ya da listenin sorgusu.',
        'too-many' => 'Bir seferde çok fazla kimlik var; bunun yerine listenin sorgusunu gönderin.',
        'unknown-action' => 'Böyle bir toplu işlem yok. Bilinenler: :known',
        'forbidden' => 'Bu işlem için :permission izni gerekir.',
        'gone' => 'Ürün artık işlemin onu bulduğu yerde değil.',
    ],
];
