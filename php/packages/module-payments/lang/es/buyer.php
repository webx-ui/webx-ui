<?php

declare(strict_types=1);

// What the buyer reads (spec §10, §12, §17): the payment step, the bank details, the order's line.
return [
    'step' => [
        'methods' => 'Método de pago',
        'save' => 'Guardar',
        'delivery_first' => 'Elija primero el envío: de él dependen los métodos de pago',
        'method_dropped' => 'El método de pago elegido no está disponible para este envío. Elija otro.',
    ],
    'errors' => [
        'choose_method' => 'Elija un método de pago',
        'method_gone' => 'El método de pago ya no está disponible, elija otro',
    ],
    'instructions' => [
        'title' => 'Datos bancarios para el pago',
        'recipient' => 'Beneficiario',
        'account' => 'Cuenta',
        'purpose' => 'Concepto del pago',
        'note' => 'Plazo',
    ],
    'status' => [
        'paid' => 'Pagado',
        'awaiting' => 'Pendiente de pago',
        'pay' => 'Pagar',
    ],
    'pay' => [
        'title' => 'Pago del pedido :number',
        'description' => 'Pedido :number',
        'due' => 'A pagar: :amount',
        'paid' => 'El pedido :number está pagado',
        'cancelled' => 'El pedido :number está cancelado',
        'checking' => 'Estamos comprobando el pago. Suele tardar hasta un minuto.',
        'failed' => 'El pago no se ha completado',
        'unavailable' => 'No se pudo abrir el pago. Inténtelo de nuevo o elija otro método.',
        'on_receipt' => 'El pedido se paga al recibirlo',
        'order_link' => 'Ir al pedido',
        'redirecting' => 'Le llevamos a la página de pago…',
        'go' => 'Ir al pago',
    ],
];
