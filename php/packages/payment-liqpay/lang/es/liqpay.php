<?php

declare(strict_types=1);

return [
    'gateway' => [
        'title' => 'LiqPay',
        'mode' => 'Modo',
        'modes' => [
            'test' => 'Prueba',
            'live' => 'Producción',
        ],
        'test_public_key' => 'Clave pública, prueba',
        'live_public_key' => 'Clave pública, producción',
        'test_private_key' => 'Clave privada, prueba',
        'live_private_key' => 'Clave privada, producción',
        'language' => 'Idioma de la página',
        'language_help' => 'LiqPay muestra su página solo en ucraniano e inglés.',
        'languages' => [
            'auto' => 'Según el idioma del pedido',
            'uk' => 'Ucraniano',
            'en' => 'Inglés',
        ],
        'expires_minutes' => 'Minutos que funciona la página de pago',
    ],
    'paytypes' => [
        'card' => 'Tarjeta',
        'apay' => 'Apple Pay',
        'gpay' => 'Google Pay',
        'privat24' => 'Privat24',
        'qr' => 'Código QR',
        'moment_part' => 'Pago a plazos instantáneo',
        'paypart' => 'Pago en partes',
        'paypart_help' => 'Primero active el servicio en el panel de LiqPay',
        'hold_cards_only' => 'La retención solo funciona con pago con tarjeta',
        'hold_needs_choice' => 'Con retención, elija qué ofrece la página: sin elegir mostrará también los plazos',
    ],
    'connection' => [
        'ok' => 'LiqPay aceptó las claves',
        'no_keys' => 'Introduzca las dos claves de este modo',
        'wrong_mode' => 'Estas claves no corresponden al modo elegido',
        'private_key' => 'La clave privada no coincide',
        'public_key' => 'No se encontró la clave pública',
        'no_answer' => 'LiqPay no respondió',
    ],
    'status' => [
        'wait_accept' => 'LiqPay aún no ha verificado la tienda: el dinero llegará tras la verificación',
        'wait_reserve' => 'El pago se realizó; el dinero está reservado para un reembolso anterior',
        'subscribed' => 'LiqPay informa de una suscripción: el sitio no las tiene',
        'unsubscribed' => 'LiqPay informa de una suscripción cancelada: el sitio no las tiene',
        'unknown' => 'Estado desconocido de LiqPay: :status',
    ],
    'errors' => [
        '9859' => 'La tarjeta no tiene fondos suficientes',
        'limit' => 'El banco lo rechazó: se alcanzó un límite de la tarjeta',
        'expired' => 'El plazo de pago ha terminado',
        'wait_info' => 'LiqPay espera más datos del comprador',
        'err_amount_hold' => 'El importe supera lo retenido',
        'payment_err_status' => 'LiqPay no lo permite en el estado actual del pago',
        'order_id_duplicate' => 'LiqPay ya tiene un pago abonado con este número',
        'invalid_signature' => 'La firma no coincide: compruebe la clave privada',
        'public_key_not_found' => 'LiqPay no conoce esta clave pública',
        'payment_not_found' => 'LiqPay no tiene ese pago: el comprador no lo inició',
        'err_wrong_currency' => 'LiqPay no acepta esta moneda',
        'wrong_amount_currency' => 'La retención no se puede liberar en parte',
        'refused' => 'LiqPay rechazó la operación',
    ],
];
