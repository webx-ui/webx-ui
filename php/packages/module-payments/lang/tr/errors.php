<?php

declare(strict_types=1);

// Refusals of the service, the API and the agent's tools (spec §17).
return [
    'mark' => [
        'paid' => 'Sipariş zaten ödendi',
        'cancelled' => 'Sipariş iptal edildi',
    ],
    'begin' => [
        'cancelled' => 'Sipariş iptal edildi',
        'nothing_due' => 'Sipariş için ödenecek bir şey yok',
        'not_online' => 'Yöntem çevrim içi değil',
        'paid' => 'Sipariş zaten ödendi',
        'held' => 'Siparişin parası bloke edildi: mağaza tahsil edecek',
    ],
    'hold' => [
        'not_held' => 'Bu ödemede bloke edilmiş para yok',
        'no_hold' => 'Bu ödeme yöntemi para bloke edemez',
        'amount' => 'Tutar sıfırdan büyük olmalı',
        'too_much' => 'Bloke edilenden fazla',
        'partial' => 'Bu ödeme geçidi yalnızca bloke edilen tutarın tamamını tahsil eder',
        'busy' => 'Ödeme güncelleniyor, bir dakika sonra tekrar deneyin',
    ],
    'problem' => [
        'none' => 'Ödemede sorun yok',
        'hold' => 'Bloke tahsil edilerek veya serbest bırakılarak çözülür',
    ],
];
