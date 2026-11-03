<?php

declare(strict_types=1);

// Refusals of the service, the API and the agent's tools (spec §17).
return [
    'mark' => [
        'paid' => 'L\'ordine è già pagato',
        'cancelled' => 'L\'ordine è annullato',
    ],
    'begin' => [
        'cancelled' => 'L’ordine è annullato',
        'nothing_due' => 'Per l’ordine non c’è nulla da pagare',
        'not_online' => 'Il metodo non è online',
    ],
];
