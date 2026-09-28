<?php

declare(strict_types=1);

// The regions of the layout (the regions spec): the words a declaration may point at with
// `trans::webx-blocks::regions.header`, and what the server says about a region.
return [
    'header' => 'Üst bilgi',
    'footer' => 'Alt bilgi',
    'usage' => '“:title” bölgesi',
    'preview-failed' => 'Bu bölgenin bir bloğu hata veriyor. Sitede bölgenin tamamı yerine koddaki işaretleme gösterilecek.',
    'not-in-registry' => ':path sitenin adres kaydında bir sayfa değil, bu yüzden bölge düzenin boş bir sayfasında gösteriliyor.',
    'too-many' => 'Bölge en fazla :max blok alır.',
    'not-allowed' => '“:type” bloğu bu bölgeye konamaz.',
    'refused' => 'Bölge bu blokları kabul etmiyor.',
    'conflict' => 'Bölge siz açtıktan sonra değiştirildi. Değişiklikleri görmek için yeniden yükleyin.',
    'failed-block' => '“:type” bloğu (:key) hata veriyor: :reason',
    'not-published' => 'Yayımlanmadı: taslağın bir bloğu çizilemiyor.',
    'nothing-to-publish' => 'Bölge hiç kaydedilmedi: yayımlanacak bir şey yok.',
    'no-version' => 'Bölgenin :number sürümü yok.',
    'no-fallback' => 'Düzen bu bölge için henüz bir görünüm belirtmedi ya da görünüm artık yok.',
    'adopt-taken' => '“:slug” blok türü zaten var.',
    'adopt-failed' => 'İşaretleme bir blok türüne dönüştürülemedi.',
    'adopt-forbidden' => 'İşaretlemeyi bir blok türüne taşımak için blok türlerini düzenleme hakkı gerekir.',
];
