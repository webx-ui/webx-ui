<?php

declare(strict_types=1);

return [
    'schema' => [
        'invalid' => 'Şema bir JSON nesnesi değil.',
        'version' => 'Bilinmeyen şema sürümü: bu modül 1. sürümü okur.',
    ],
    'currency' => [
        'invalid' => 'Para birimi büyük harfli üç harfli bir ISO 4217 kodudur, örneğin UAH veya EUR.',
    ],
    'pricing' => [
        'round' => 'Yuvarlama adımı 0’dan büyük bir sayıdır.',
    ],
    'discount' => [
        'percent' => 'İndirim 0’dan büyük ve 100’den küçük bir yüzdedir.',
        'until' => 'İndirimin son günü YYYY-AA-GG biçiminde bir tarihtir.',
    ],
    'steps' => [
        'empty' => 'Bir yapılandırıcının en az bir adımı olmalı.',
    ],
    'key' => [
        'invalid' => '«:key» anahtarı harfle başlayan a-z, 0-9 ve _ değil.',
        'duplicate' => '«:key» anahtarı kullanılıyor: adımlar ve ölçü alanları anahtarları paylaşır.',
    ],
    'type' => [
        'unknown' => 'Bilinmeyen adım türü: cards, swatches, buttons veya dimensions.',
    ],
    'options' => [
        'empty' => 'Bir seçim adımının en az bir seçeneği olmalı.',
    ],
    'fields' => [
        'empty' => 'Bir ölçü adımının en az bir alanı olmalı.',
    ],
    'value' => [
        'invalid' => '«:value» değeri bir slug değil: a-z, 0-9, - ve _.',
        'duplicate' => '«:value» değeri bu adımda iki kez geçiyor.',
    ],
    'label' => [
        'missing' => 'Varsayılan dilde (:locale) bir ad gerekli.',
    ],
    'text' => [
        'invalid' => 'Bir metin, bir dize ya da diller kümesidir.',
    ],
    'image' => [
        'missing' => 'Medya kitaplığından bir görsel seçin.',
        'unknown' => 'Medya kitaplığında «:key» dosyası yok.',
    ],
    'product' => [
        'invalid' => 'Ürün, en fazla 64 karakterlik stok koduyla (SKU) belirtilir.',
        'unknown' => 'Katalogda «:sku» stok kodlu ürün yok.',
    ],
    'swatch' => [
        'invalid' => 'Bir örnek #rgb, #rrggbb ya da hex renklerden bir geçiştir.',
    ],
    'price' => [
        'invalid' => 'Fiyat bir sayıdır.',
        'per_m2' => 'm² başına fiyat bir alan ister: bir adımın seçeneklerine alan formülü verin.',
    ],
    'area' => [
        'main_count' => 'Alan formülü yalnızca bir adımın seçeneklerinde olabilir.',
        'missing' => 'Ana adımın her seçeneğinin bir alan formülü olmalı.',
        'syntax' => 'Formül :position. karakterde hatalı: :reason.',
        'unknown_key' => 'Formül «:key» kullanıyor, bu bir ölçü alanı değil.',
    ],
    'range' => [
        'invalid' => 'Aralık hatalı: min, max’tan büyük ya da adım 0’dan büyük değil.',
    ],
    'limits' => [
        'unknown_field' => 'limits «:key» diyor, bu bir ölçü alanı değil.',
    ],
    'defaults' => [
        'unknown_field' => 'defaults «:key» diyor, bu bir ölçü alanı değil.',
    ],
    'condition' => [
        'invalid' => 'Koşul, bir adım ve onun seçildiği değerlerdir.',
        'unknown_step' => '«:step» adımı yok.',
        'forward' => '«:step» adımı bunun üstünde değil: koşul yalnızca yukarı bakar.',
        'not_choice' => '«:step» bir ölçü adımı: koşul seçenekli bir adımı adlandırır.',
        'unknown_value' => '«:step» adımının «:value» seçeneği yok.',
    ],
    'initial' => [
        'unknown' => '«:key» başlangıç seçimi bir adım, alan ya da onun değeri değil.',
    ],
    'previews' => [
        'condition' => 'Bir görsel kuralı olmayan bir adımı ya da değeri adlandırıyor.',
    ],
    'values' => [
        'range' => '«:key» ölçüsü :min ile :max arasındadır.',
        'number' => '«:key» ölçüsü bir sayıdır.',
    ],
];
