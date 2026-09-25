<?php

declare(strict_types=1);

return [
    'kind' => 'Bir tür ya blok ya da bileşendir.',
    'kind-in-use' => 'Blok sayfalarda kullanılıyor ve bileşene dönüşemez. Sayfa sayısı: :count.',
    'unknown-call' => '":type" bir blok türü değil: çağrı sitede hiçbir şey yazdırmaz.',
    'dynamic-call' => 'Çağrılan tür açıkça yazılmamış: yayımlanması bu şablonu denetlemez.',
    'delete-used-by' => 'Başka türler bunu çağırıyor ve şablonlarında boşluk kalır. Önce çağrıları kaldırın.',
    'delete-used-by-one' => '":title" (:slug) tarafından çağrılıyor',
    'publish-cycle' => 'Türler birbirini döngü halinde çağırıyor: :path.',
    'publish-breaks-parent' => '":parent" örneğinde bozuluyor: :reason',
    'publish-breaks-parent-on' => '":parent", ":entity" üzerinde bozuluyor: :reason',
    'publish-breaks-declared' => ':module modülünün onu çağırdığı yer bozuluyor: :reason',
    'customise-exists' => 'Bu tanımlayıcıya sahip bir tür zaten var.',
    'customised-from' => 'Kaynak: :view',
];
