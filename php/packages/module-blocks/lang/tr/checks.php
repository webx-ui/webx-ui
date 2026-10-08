<?php

declare(strict_types=1);

return [
    'no-marker' => 'Kökte data-wx-block yok: betik çalışmaz ve panel önizlemede bloğu vurgulayamaz.',
    'stray-selectors' => 'Blok öneki .b-:slug dışındaki seçiciler: :selectors',
    'bare-selectors' => 'Öğe seçicileri tüm siteye ulaşır: :selectors',
    'media-query' => '@media pencereyi ölçer. Bir blok kapsayıcısına göre boyutlanır: @container kullanın.',
    'string-on-text' => 'İçinde bir kısa kod olduğunda :field HTML’dir: bir dize işlevi ya da dönüştürme bu HTML’i {{ }} öğesine verir ve o da ikinci kez kaçışlar. Alanı wx_text() ile değiştirin: {{ wx_text(:field)->trimEnd(".") }} — trim, trimStart, stripPrefix, stripSuffix ve map() onu HTML olarak tutar.',
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
        'unknown_shortcodes' => [
            'title' => 'Yanlış yazılmış kısa kodlar',
            'found' => 'Bir metinde sitenin bir kısa koduna çok benzeyen ya da argüman taşıyan, ama kısa kod olmayan bir köşeli parantez var.',
            'why' => 'Yalnızca kayıtlı kısa kodlar değiştirilir. Gerisi parantezleriyle birlikte yazıldığı gibi basılır ve her ziyaretçi görür.',
            'fix' => 'Adı önerilenle düzeltin ya da sayfa parantezleri göstermeliyse [[ad]] yazın. Liste alanın kısa kod yardımında ve «Ayarlar» → «Kısa kodlar» altındadır.',
        ],
        'hardcoded_values' => [
            'title' => 'Kısa kod yerine değerler',
            'found' => 'Bir metin, «Ayarlar» → «Kısa kodlar» içindeki bir kısa kodun zaten tuttuğu bir telefon, e-posta ya da başka bir değer içeriyor.',
            'why' => 'Bugün doğru, değer değiştiği gün yanlış: kısa kod her yerde değişir, elle yazılan değer yalnızca birinin hatırladığı yerde.',
            'fix' => 'Değeri önerilen kısa kodla değiştirin, ör. [phone]. Bağlantıyı kısa kodun kendisi oluşturur.',
        ],
    ],
    'syntax' => 'Şablon derlenmiyor: :reason. Yayınlama reddedilecek.',
    'unknown-field-type' => 'Sitenin tanımadığı türde alanlar: :fields. Form yerlerinde bir uyarı gösterir ve değerlerini kimse denetlemez.',
    'field-id' => 'Alan kimliği harf, rakam, _ ve - içerir ve harfle başlar: :ids.',
    'marker-slug' => 'Kök data-wx-block=":marker" ile işaretli, ancak tanımlayıcı «:slug»: betik ve panel bloğu tam tanımlayıcıyla bulur. Yayınlama reddedilecek.',
];
