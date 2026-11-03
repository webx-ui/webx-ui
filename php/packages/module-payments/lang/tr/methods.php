<?php

declare(strict_types=1);

// The built-in payment methods (spec §12, §17): their default titles, notes and settings.
return [
    'on_receipt' => [
        'title' => 'Teslimatta ödeme',
        'pickup' => 'Kasada ödeme',
        'cod_note' => 'Kargo şirketi kapıda ödeme için kendi ücretini alır',
        'settings' => [
            'title_pickup' => 'Mağazadan teslim alma başlığı',
            'cod_note' => 'Kapıda ödeme ücreti notu',
        ],
    ],
    'bank_transfer' => [
        'title' => 'Banka havalesi',
        'purpose' => '{number} numaralı sipariş ödemesi',
        'settings' => [
            'recipient' => 'Alıcı',
            'account' => 'Hesap ve banka',
            'purpose' => 'Ödeme açıklaması ({number} — sipariş numarası)',
            'note' => 'Ödeme süresi',
        ],
    ],
];
