<?php

declare(strict_types=1);

// The built-in payment methods (spec §12, §17): their default titles, notes and settings.
return [
    'on_receipt' => [
        'title' => 'Pagamento alla consegna',
        'pickup' => 'Pagamento in cassa',
        'cod_note' => 'Il corriere applica una propria commissione per il contrassegno',
        'settings' => [
            'title_pickup' => 'Nome per il ritiro in negozio',
            'cod_note' => 'Nota sulla commissione del contrassegno',
        ],
    ],
    'bank_transfer' => [
        'title' => 'Bonifico bancario',
        'purpose' => 'Pagamento dell\'ordine {number}',
        'settings' => [
            'recipient' => 'Beneficiario',
            'account' => 'Conto e banca',
            'purpose' => 'Causale ({number} — numero dell\'ordine)',
            'note' => 'Termine di pagamento',
        ],
    ],
];
