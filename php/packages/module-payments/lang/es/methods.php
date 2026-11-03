<?php

declare(strict_types=1);

// The built-in payment methods (spec §12, §17): their default titles, notes and settings.
return [
    'on_receipt' => [
        'title' => 'Pago al recibir',
        'pickup' => 'Pago en caja',
        'cod_note' => 'La empresa de envío cobra su propia comisión por el contra reembolso',
        'settings' => [
            'title_pickup' => 'Nombre para la recogida en tienda',
            'cod_note' => 'Nota sobre la comisión del contra reembolso',
        ],
    ],
    'bank_transfer' => [
        'title' => 'Transferencia bancaria',
        'purpose' => 'Pago del pedido {number}',
        'settings' => [
            'recipient' => 'Beneficiario',
            'account' => 'Cuenta y banco',
            'purpose' => 'Concepto del pago ({number} — número de pedido)',
            'note' => 'Plazo de pago',
        ],
    ],
];
