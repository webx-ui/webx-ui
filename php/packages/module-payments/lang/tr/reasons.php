<?php

declare(strict_types=1);

// Why a method is not offered (spec §7.2): for the manager and the agent, never the buyer.
return [
    'nothing_to_pay' => 'Ödenecek tutar yok',
    'type_missing' => ':type türü yüklü değil',
    'currency' => 'Yöntem :currency kabul etmiyor',
    'amount_below_min' => 'Tutar yöntemin alt sınırının altında',
    'amount_above_max' => 'Tutar yöntemin üst sınırının üstünde',
    'delivery_pending' => 'Teslimat seçimini bekliyor',
    'delivery_rule' => 'Seçilen teslimat için sunulmuyor',
    'no_credentials' => 'Ağ geçidi anahtarları ayarlanmadı',
    'gateway_missing' => ':gateway ödeme geçidi kurulu değil',
];
