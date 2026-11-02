<?php

declare(strict_types=1);

return [
    'carrier' => [
        'title' => 'Nova Poshta',
    ],
    'types' => [
        'branch' => [
            'title' => 'Sucursal de Nova Poshta',
            'description' => 'Recogida en una sucursal del transportista; pagas el envío al transportista al recogerlo.',
        ],
        'locker' => [
            'title' => 'Taquilla de Nova Poshta',
            'description' => 'Recogida en una taquilla del transportista; pagas el envío al transportista al recogerlo.',
        ],
        'courier' => [
            'title' => 'Mensajero de Nova Poshta',
            'description' => 'El transportista lleva el paquete hasta tu puerta; pagas el envío al transportista al recibirlo.',
        ],
    ],
    'settings' => [
        'payment_method' => 'Pago del envío',
        'payment_methods' => [
            'Cash' => 'Efectivo',
            'NonCash' => 'Sin efectivo (con contrato con el transportista)',
        ],
        'cargo_type' => 'Tipo de carga por defecto',
        'cargo_types' => [
            'Parcel' => 'Paquete',
            'Cargo' => 'Carga',
            'Documents' => 'Documentos',
        ],
        'weight' => 'Peso por defecto, kg',
        'description' => 'Descripción de la carga por defecto',
        'description_default' => 'Mercancías',
    ],
    'credentials' => [
        'api_key' => 'Clave API',
    ],
    'connection' => [
        'ok' => 'Remitentes en la cuenta: :count',
        'key_refused' => 'El transportista no aceptó la clave API.',
        'no_answer' => 'El transportista no respondió.',
        'no_sender' => 'La cuenta del transportista no tiene remitente.',
        'no_key' => 'Introduce primero la clave API.',
    ],
];
