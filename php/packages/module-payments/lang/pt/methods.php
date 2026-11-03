<?php

declare(strict_types=1);

// The built-in payment methods (spec §12, §17): their default titles, notes and settings.
return [
    'on_receipt' => [
        'title' => 'Pagamento na entrega',
        'pickup' => 'Pagamento no caixa',
        'cod_note' => 'A transportadora cobra a sua própria taxa pelo pagamento na entrega',
        'settings' => [
            'title_pickup' => 'Nome para levantamento na loja',
            'cod_note' => 'Nota sobre a taxa de pagamento na entrega',
        ],
    ],
    'bank_transfer' => [
        'title' => 'Transferência bancária',
        'purpose' => 'Pagamento da encomenda {number}',
        'settings' => [
            'recipient' => 'Beneficiário',
            'account' => 'Conta e banco',
            'purpose' => 'Descrição do pagamento ({number} — número da encomenda)',
            'note' => 'Prazo de pagamento',
        ],
    ],
];
