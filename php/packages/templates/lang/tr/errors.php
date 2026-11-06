<?php

declare(strict_types=1);

return [
    'template-unclosed' => '{{#:section}} bölümü kapatılmamış: {{/:section}} ile bitirin.',
    'template-unexpected' => '{{/:section}} hiç açılmamış bir bölümü kapatıyor.',
    'template-tag' => '{{:tag etiketi kapatılmamış: }} ile bitirin.',
    'template-name' => '{{:name}} bir değişken adı değil: küçük harfler, rakamlar ve _, en fazla bir nokta.',
    'template-partial' => 'Kısmi şablonlar ({{>name}}) mesaj şablonunda kullanılamaz.',
    'template-delimiters' => 'Ayraçları değiştirmek ({{=<% %>=}}) mesaj şablonunda kullanılamaz.',
    'template-pragma' => 'Pragmalar ve bloklar ({{%…}}, {{$…}}) mesaj şablonunda kullanılamaz.',
    'template-cdata' => 'Bir değişken düz bir CDATA bloğunun içinde duramaz: {{:name}} iki kez kaçışlanırdı. Çevresindeki CDATA’yı kaldırın ve _cdata değişkenini kullanın, örneğin {{description_cdata}}.',
];
