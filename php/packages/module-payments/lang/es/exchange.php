<?php

declare(strict_types=1);

// The journal of the exchange with gateways (spec §20): directions, kinds, signatures, outcomes.
return [
    'title' => 'Intercambio con la pasarela',
    'directions' => [
        'out' => 'A la pasarela',
        'in' => 'De la pasarela',
        'browser' => 'Por el navegador del comprador',
        'internal' => 'Decisión del sitio',
    ],
    'kinds' => [
        'start' => 'Pago abierto',
        'status' => 'Consulta de estado',
        'cancel' => 'Cancelación',
        'complete' => 'Aprobación completada',
        'capture' => 'Solicitud de cobro',
        'void' => 'Solicitud de liberación',
        'connection' => 'Prueba de conexión',
        'key' => 'Clave de firma',
        'other' => 'Otra solicitud',
        'notify' => 'Notificación',
        'handoff' => 'Enviado a la pasarela',
        'return' => 'Vuelta de la pasarela',
        'expire' => 'Caducado',
        'manual' => 'Marcado a mano',
        'captured' => 'Cobrado',
        'released' => 'Retención liberada',
    ],
    'signature' => [
        'valid' => 'Firma válida',
        'invalid' => 'Firma no válida',
        'untrusted' => 'Sin firma — estado consultado a la pasarela',
        'none' => 'No comprobada',
    ],
    'outcomes' => [
        'applied' => 'Aplicado',
        'same' => 'Nada nuevo',
        'ignored' => 'Ignorado',
        'duplicate' => 'Repetición',
        'rejected' => 'Rechazado',
        'unmatched' => 'No existe ese pago',
        'error' => 'Error',
    ],
    'truncated' => 'Cortado en el límite',
    'download' => 'Descargar',
    'size' => 'Tamaño',
    'masked' => 'Secretos ocultos',
];
