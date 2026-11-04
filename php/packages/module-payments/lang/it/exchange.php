<?php

declare(strict_types=1);

// The journal of the exchange with gateways (spec §20): directions, kinds, signatures, outcomes.
return [
    'title' => 'Scambio con il gateway',
    'directions' => [
        'out' => 'Al gateway',
        'in' => 'Dal gateway',
        'browser' => 'Tramite il browser dell\'acquirente',
        'internal' => 'Decisione del sito',
    ],
    'kinds' => [
        'start' => 'Pagamento aperto',
        'status' => 'Richiesta di stato',
        'cancel' => 'Annullamento',
        'complete' => 'Approvazione completata',
        'capture' => 'Richiesta di incasso',
        'void' => 'Richiesta di rilascio',
        'connection' => 'Verifica connessione',
        'key' => 'Chiave di firma',
        'other' => 'Altra richiesta',
        'notify' => 'Notifica',
        'handoff' => 'Inviato al gateway',
        'return' => 'Ritorno dal gateway',
        'expire' => 'Scaduto',
        'manual' => 'Segnato a mano',
        'captured' => 'Incassato',
        'released' => 'Preautorizzazione rilasciata',
        'capture_refused' => 'Addebito rifiutato',
        'release_refused' => 'Svincolo rifiutato',
    ],
    'signature' => [
        'valid' => 'Firma valida',
        'invalid' => 'Firma non valida',
        'untrusted' => 'Non firmato — stato chiesto al gateway',
        'none' => 'Non verificata',
    ],
    'outcomes' => [
        'applied' => 'Applicato',
        'same' => 'Niente di nuovo',
        'ignored' => 'Ignorato',
        'duplicate' => 'Ripetizione',
        'rejected' => 'Rifiutato',
        'unmatched' => 'Pagamento inesistente',
        'error' => 'Errore',
    ],
    'truncated' => 'Tagliato al limite',
    'download' => 'Scarica',
    'size' => 'Dimensione',
    'masked' => 'Segreti nascosti',
];
