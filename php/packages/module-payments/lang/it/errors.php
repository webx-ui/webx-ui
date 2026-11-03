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
        'paid' => 'L’ordine è già pagato',
        'held' => 'Il denaro dell’ordine è bloccato: il negozio lo addebiterà',
    ],
    'hold' => [
        'not_held' => 'Questo pagamento non ha denaro bloccato',
        'no_hold' => 'Questo metodo di pagamento non può bloccare denaro',
        'amount' => 'L’importo deve essere maggiore di zero',
        'too_much' => 'Più di quanto bloccato',
        'partial' => 'Questo gateway addebita solo l’intero importo bloccato',
        'busy' => 'Il pagamento è in aggiornamento, riprova tra un minuto',
    ],
    'problem' => [
        'none' => 'Il pagamento non ha problemi',
        'hold' => 'Un blocco si risolve addebitandolo o rilasciandolo',
    ],
];
