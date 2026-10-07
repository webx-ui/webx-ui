<?php

declare(strict_types=1);

return [
    'no-marker' => 'Kökte data-wx-block yok: betik çalışmaz ve panel önizlemede bloğu vurgulayamaz.',
    'stray-selectors' => 'Blok öneki .b-:slug dışındaki seçiciler: :selectors',
    'bare-selectors' => 'Öğe seçicileri tüm siteye ulaşır: :selectors',
    'media-query' => '@media pencereyi ölçer. Bir blok kapsayıcısına göre boyutlanır: @container kullanın.',
    'variables-missing' => 'Şablon, şemanın bildirmediği :variables değişkenlerini kullanıyor. Yayımlama reddedilecek.',
    'ok-marker' => 'Kök data-wx-block taşıyor.',
    'ok-prefix' => 'Her seçici .b-:slug ile başlıyor.',
    'ok-bare' => 'Çıplak öğe seçicisi yok.',
    'ok-container' => 'Genişliğe kapsayıcı sorguları karar veriyor.',
    'ok-variables' => 'Şablonun her değişkeni şemanın bir alanı.',
    'blocks' => [
        'stray_values' => [
            'title' => 'Blok türünde olmayan alanların değerleri',
            'found' => 'Bloklar, türlerinin tanımlamadığı alanların değerlerini tutuyor — bir içe aktarmadan ya da türden çıkarılan bir alandan kalma.',
            'why' => 'Ziyaretçi bunları görmez, ama düzenleyicinin verisinde ve bir ajanın okuduğu içerikte durur, bloğun yanlış etiketi olarak ortaya çıkar.',
            'fix' => 'Bunları düzeltmeyle ya da tüm site için php artisan webx:blocks:prune ile kaldırın. Artık var olmayan bir türün bloğuna dokunulmaz. Tekrarlayıcı öğeleri kendi alanlarıyla karşılaştırılır.',
        ],
    ],
    'syntax' => 'Şablon derlenmiyor: :reason. Yayınlama reddedilecek.',
    'unknown-field-type' => 'Sitenin tanımadığı türde alanlar: :fields. Form yerlerinde bir uyarı gösterir ve değerlerini kimse denetlemez.',
    'field-id' => 'Alan kimliği harf, rakam, _ ve - içerir ve harfle başlar: :ids.',
    'marker-slug' => 'Kök data-wx-block=":marker" ile işaretli, ancak tanımlayıcı «:slug»: betik ve panel bloğu tam tanımlayıcıyla bulur. Yayınlama reddedilecek.',
];
