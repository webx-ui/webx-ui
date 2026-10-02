<?php

declare(strict_types=1);

return [
    'menu' => [
        'broken' => [
            'title' => 'Hataya giden menü öğeleri',
            'found' => 'Bir menü öğesi hata yanıtı veren bir sayfaya ya da artık adresi olmayan bir kayda gidiyor.',
            'why' => 'Menü her sayfada bulunur: tek bozuk öğe her yerde bozuk bir bağlantıdır ve ziyaretçinin ilk tıkladığı şeydir.',
            'fix' => 'Öğeyi var olan bir sayfaya yöneltin ya da kaldırın.',
        ],
        'redirect' => [
            'title' => 'Yönlendirmeye giden menü öğeleri',
            'found' => 'Bir menü öğesi başka bir yere yönlendiren bir adrese gidiyor.',
            'why' => 'Menünün yazdırıldığı her sayfada her tıklama fazladan bir gidiş-dönüşe mal olur.',
            'fix' => 'Öğeyi yönlendirmenin vardığı adrese yöneltin.',
        ],
    ],
];
