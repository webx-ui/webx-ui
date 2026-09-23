<?php

declare(strict_types=1);

/*
 * The words of the categories every module shares (`WebxUi\Admin\Categories`). A module that
 * calls its categories something else — rubrics — says its own refusal instead.
 */

return [
    'in-use' => 'İçindeki kayıt: :count. Önce onları başka bir kategoriye taşıyın.',
    'slug-shape' => 'Harfler, rakamlar ve aralarında tek tire.',

    // The shared screens of categories (`categories/` in @webx-ui/module-admin). A module
    // that calls its categories something else hands its own keys in over these.
    'new' => 'Yeni kategori',
    'empty' => 'Henüz kategori yok.',
    'empty-help' => 'Kategori kayıtları bir araya getirir. Bir kayıt birden fazla kategoride olabilir.',
    'order' => 'Buradaki sıra sitedeki sıradır',
    'hidden' => 'Sitede gizli',
    'no-address' => 'Bu dilde adres yok',
    'count' => 'Kayıt: :count',
    'show-items' => 'Kayıtlarını göster',
    'edit' => 'Düzenle',
    'open-on-site' => 'Sitede aç',
    'delete' => 'Sil',
    'delete-blocked' => 'İçinde kayıt varken silinemez — önce onları taşıyın.',
    'delete-title' => '“:name” silinsin mi?',
    'delete-text' => 'Çöp kutusuna gider ve siteden kalkar, adresi yeniden boşa çıkar.',
    'deleted' => 'Kategori çöp kutusunda.',
    'cancel' => 'İptal',
    'create' => 'Oluştur',
    'save' => 'Kaydet',
    'saved' => 'Kaydedildi.',
    'save-failed' => 'Kaydedilmedi — işaretli alanlara bakın.',
    'reorder-failed' => 'Yeni sıra kaydedilmedi.',
    'field-title' => 'Ad',
    'field-slug' => 'Adres',
    'address-moving' => 'Adres değişiyor. Eskisi çalışmaya devam eder ve yenisine yönlendirir.',
    'untitled' => 'Başlıksız',
    'trail' => 'Bulunduğunuz yer',
    'leave-title' => 'Kaydetmeden çıkılsın mı?',
    'leave-text' => 'Son kayıttan sonra burada değiştirilenler kaybolacak.',
    'leave' => 'Çık',
    'field-main' => 'Ana',
    'field-add' => 'Kategori ekle',
    'field-remove' => 'Bu kategoriden çıkar',
    'field-empty' => 'Henüz hiçbir kategoride değil.',
    'field-none-left' => 'Tüm kategoriler zaten seçili.',
    'order-all' => 'Sitedeki sırayı değiştirmek için sürükleyin.',
    'order-category' => 'Bu kategorinin içindeki sırayı değiştirmek için sürükleyin. Listenin geri kalanı kendi sırasını korur.',
    'order-locked' => 'Sırayı değiştirmek için aramayı ve filtreleri temizleyin — yalnızca tüm liste ya da tek bir kategori sürüklenebilir.',
    'unknown' => 'Seçilen kategorilerden biri artık yok.',
];
