<?php

declare(strict_types=1);

return [
    'carrier' => [
        'title' => 'Nova Poshta',
    ],
    'types' => [
        'branch' => [
            'title' => 'Agência Nova Poshta',
            'description' => 'Levantamento numa agência da transportadora; paga o envio à transportadora no levantamento.',
        ],
        'locker' => [
            'title' => 'Cacifo Nova Poshta',
            'description' => 'Levantamento num cacifo da transportadora; paga o envio à transportadora no levantamento.',
        ],
        'courier' => [
            'title' => 'Estafeta Nova Poshta',
            'description' => 'A transportadora entrega a encomenda à sua porta; paga o envio à transportadora na entrega.',
        ],
    ],
    'settings' => [
        'payment_method' => 'Pagamento do envio',
        'payment_methods' => [
            'Cash' => 'Dinheiro',
            'NonCash' => 'Sem dinheiro (com contrato com a transportadora)',
        ],
        'cargo_type' => 'Tipo de carga predefinido',
        'cargo_types' => [
            'Parcel' => 'Encomenda',
            'Cargo' => 'Carga',
            'Documents' => 'Documentos',
        ],
        'weight' => 'Peso predefinido, kg',
        'description' => 'Descrição da carga predefinida',
        'description_default' => 'Mercadorias',
    ],
    'credentials' => [
        'api_key' => 'Chave API',
    ],
    'connection' => [
        'ok' => 'Remetentes na conta: :count',
        'key_refused' => 'A transportadora não aceitou a chave API.',
        'no_answer' => 'A transportadora não respondeu.',
        'no_sender' => 'A conta da transportadora não tem remetente.',
        'no_key' => 'Introduza primeiro a chave API.',
    ],
];
