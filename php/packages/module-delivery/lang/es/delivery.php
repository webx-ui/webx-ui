<?php

declare(strict_types=1);

return [
    'title' => 'Envío',
    'cache' => 'Métodos de envío y transportistas',
    'cache_points' => 'Puntos de entrega de los transportistas',
    'carriers' => [
        'store' => 'La tienda',
    ],
    'point_kinds' => [
        'branch' => 'Oficina',
        'locker' => 'Taquilla',
        'store' => 'Tienda',
        'pickup' => 'Punto de recogida',
    ],
    'locality_kinds' => [
        'city' => 'Ciudad',
        'town' => 'Ciudad',
        'township' => 'Municipio',
        'village' => 'Pueblo',
        'settlement' => 'Aldea',
        'other' => 'Localidad',
    ],
    'step' => [
        'delivery' => 'Envío',
        'locality' => 'Ciudad o localidad',
        'locality_placeholder' => 'Empieza a escribir el nombre',
        'where' => '¿Dónde entregamos el pedido?',
        'choose_locality' => 'Elige una ciudad',
        'change' => 'Cambiar',
        'popular' => 'Ciudades populares',
        'show_methods' => 'Mostrar métodos de envío',
        'no_methods' => 'No hay métodos de envío disponibles aquí',
        'free' => 'Gratis',
        'carrier_rates' => 'Según tarifas del transportista',
        'choose_point' => 'Elige una oficina',
        'pick_up_here' => 'Recoger aquí',
        'open_until' => 'Hoy abierto hasta las :time',
        'closed_today' => 'Hoy cerrado',
        'street' => 'Calle',
        'house' => 'Número',
        'apartment' => 'Piso',
        'note' => 'Nota para el repartidor',
        'cost' => 'Coste de envío',
        'points' => 'Puntos: :count',
    ],
    // The line of the cart's totals, and the benefit the shop's cart rules may grant (§9.4).
    'totals' => [
        'delivery' => 'Envío',
    ],
    'benefits' => [
        'paid_by_store' => 'La tienda paga el envío',
    ],
    'hints' => [
        'no_streets' => 'La entrega a domicilio no está disponible aquí, elige otro método',
    ],
    // A street of a carrier's directory as shown: its name with the type word where the language puts it.
    'street_kinds' => [
        'street' => 'calle :name',
        'avenue' => 'avenida :name',
        'lane' => 'callejón :name',
        'square' => 'plaza :name',
        'other' => ':name',
    ],
    'errors' => [
        'choose_point' => 'Elige un punto de recogida.',
        'point_gone' => 'El punto de recogida ya no está disponible, elige otro.',
        'choose_street' => 'Elige la calle de la lista.',
        'street_required' => 'Indica la calle.',
        'house_required' => 'Indica el número.',
        'too_long' => 'Como máximo :max caracteres.',
        'no_quote' => 'No se pudo calcular el envío, elige otro método.',
    ],
    'sync' => [
        'running' => 'Ya en curso, página :page',
        'errors' => [
            'no_answer' => 'El transportista no respondió',
            'key_rejected' => 'El transportista rechazó la clave de API',
            'bad_response' => 'La respuesta del transportista no tenía el formato esperado',
            'failed' => 'La sincronización falló en el sitio — consulta el registro',
            'interrupted' => 'La sincronización se interrumpió',
        ],
    ],
    'schedule' => [
        'closed' => 'Cerrado',
        'around_the_clock' => 'Abierto 24 horas',
        'until' => 'Hasta las :time',
        'from' => 'Abre a las :time',
    ],
];
