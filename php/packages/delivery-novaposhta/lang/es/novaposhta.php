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
        'sender' => 'Remitente',
        'contact' => 'Persona de contacto',
        'sender_city' => 'Ciudad de envío',
        'sender_point' => 'Sucursal de envío',
        'locker_width' => 'Taquilla: ancho por defecto, cm',
        'locker_height' => 'Taquilla: alto por defecto, cm',
        'locker_length' => 'Taquilla: largo por defecto, cm',
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
    'waybill' => [
        'weight' => 'Peso, kg',
        'seats' => 'Bultos',
        'cargo_type' => 'Tipo de carga',
        'description' => 'Descripción de la carga',
        'date' => 'Fecha de envío',
        'width' => 'Ancho, cm',
        'height' => 'Alto, cm',
        'length' => 'Largo, cm',
    ],
    'errors' => [
        'no_cod' => 'Este transportista aún no admite el pago contra reembolso.',
        'name_empty' => 'Rellena el nombre y el apellido del destinatario.',
        'name_letters' => 'El transportista acepta el nombre solo en letras ucranianas.',
        'phone' => 'El transportista acepta un móvil ucraniano: +380 y nueve cifras.',
        'no_sender' => 'Primero elige en los ajustes del transportista el remitente, la persona de contacto, la ciudad y la sucursal de envío.',
        'contact_gone' => 'La persona de contacto de los ajustes ya no está en la cuenta del transportista: elige otra.',
        'no_town' => 'El transportista no conoce la ciudad del pedido: vincúlala en la cola de localidades.',
        'no_point' => 'El pedido no tiene sucursal ni taquilla del transportista.',
        'no_street' => 'El pedido no tiene calle del transportista o número de casa.',
        'weight' => 'El peso debe ser mayor que cero.',
        'weight_too_much' => 'El peso supera lo que admite el punto.',
        'seats' => 'Los bultos son un número entero desde uno.',
        'locker_seats' => 'Una taquilla admite un bulto.',
        'cargo_type' => 'Elige un tipo de carga de la lista.',
        'size' => 'Las medidas deben ser mayores que cero.',
        'locker_size' => 'Una taquilla admite hasta 40 × 30 × 60 cm.',
        'date' => 'La fecha de envío es hoy o un día dentro de tres meses.',
        'read_only' => 'Este sitio usa la clave del transportista solo para lectura: las guías están desactivadas.',
        'no_ref' => 'No se guarda el identificador de esta guía en el transportista: anúlala en la cuenta del transportista.',
        'carrier_said' => 'El transportista se negó: :text',
    ],
    'remarks' => [
        'novaposhta_service_point' => 'Omitidos, puntos de servicio del transportista: :count',
        'novaposhta_deny_to_select' => 'Omitidos, cerrados a la elección: :count',
        'novaposhta_invalid_point' => 'Omitidos, con datos incompletos: :count',
    ],
];
