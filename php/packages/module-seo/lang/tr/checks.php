<?php

declare(strict_types=1);

return [
    'seo' => [
        'redirect_chain' => [
            'title' => 'Başka yönlendirmelere giden yönlendirmeler',
            'found' => 'SEO tablosundaki bir yönlendirme, kendisi de yönlendirilen bir adresi gösteriyor.',
            'why' => 'Her atlama ziyaretçi için bir gidiş-dönüş daha demektir ve arama motorları birkaçından sonra takip etmeyi bırakır.',
            'fix' => 'İlk yönlendirmeyi doğrudan son adrese yöneltin — düzeltme düğmesi bunu zincirdeki her tam yönlendirme için yapar.',
        ],
        'title_duplicate' => [
            'title' => 'Birden çok SEO kartında aynı başlık',
            'found' => 'Birkaç varlığın SEO kartında aynı dilde aynı başlık yazılı.',
            'why' => 'Kendine aynı adı veren iki sayfa aramada birbiriyle yarışır ve hiçbiri cevap gibi görünmez.',
            'fix' => 'Her sayfaya yalnızca o sayfadakini anlatan bir başlık verin.',
        ],
        'redirect_broken' => [
            'title' => 'Bozuk bir sayfaya giden yönlendirmeler',
            'found' => 'Tam bir yönlendirme, ziyaretçileri tarama sırasında hata veren bir adrese gönderiyor.',
            'why' => 'Eski bir bağlantıyı izleyen ziyaretçi bir hata sayfasına düşer ve eski adresin ağırlığı kaybolur.',
            'fix' => 'Yönlendirmeyi var olan bir sayfaya yöneltin ya da sayfayı geri getirin.',
        ],
        'rule_dead' => [
            'title' => 'Artık var olmayan adresler için SEO kuralları',
            'found' => 'Tarama sırasında 404 veya 410 yanıtı veren bir adres için tam bir SEO kuralı yazılmış.',
            'why' => 'Zararlı değil, ama kural kimse için değil ve yazıldığı sayfanın kaybolduğunu gizliyor.',
            'fix' => 'Kuralı silin ya da sayfa taşındıysa o adresten bir yönlendirme ekleyin.',
        ],
    ],
];
