<?php

declare(strict_types=1);

// Statuses of attempts, their problems and where they started (spec §8.1, §9.4, §17).
return [
    'statuses' => [
        'created' => 'Creato',
        'pending' => 'In corso',
        'authorized' => 'Bloccato',
        'paid' => 'Pagato',
        'failed' => 'Fallito',
        'cancelled' => 'Annullato',
        'expired' => 'Scaduto',
    ],
    'problems' => [
        'duplicate' => 'Pagato due volte',
        'amount_mismatch' => 'Importo errato',
        'order_cancelled' => 'Ordine annullato',
        'hold_expiring' => 'Il blocco scade',
        'capture_failed' => 'Incasso fallito',
    ],
    'sources' => [
        'checkout' => 'Checkout',
        'pay-page' => 'Pagina di pagamento',
        'poll' => 'Verifica in background',
        'panel' => 'Pannello',
        'mcp' => 'Agente',
        'notify' => 'Notifica',
        'return' => 'Ritorno',
        'cli' => 'Console',
        'api' => 'API',
        'code' => 'Codice',
    ],
];
