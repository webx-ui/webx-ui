<?php

declare(strict_types=1);

return [
    'title' => 'Indice di ricerca',
    'lead' => 'Il catalogo cerca, filtra e conta con Manticore. Quando Manticore non risponde, il pannello risponde dal database.',
    'refresh' => 'Aggiorna',
    'load-failed' => 'Impossibile caricare lo stato dell’indice.',

    'connection' => 'Server',
    'address' => 'Indirizzo',
    'prefix' => 'Prefisso delle tabelle',
    'version' => 'Versione',
    'available' => 'Risponde',
    'unavailable' => 'Non risponde',

    'tables' => 'Tabelle',
    'language' => 'Lingua',
    'table' => 'Tabella',
    'in-index' => 'Nell’indice',
    'in-database' => 'Nel database',
    'state' => 'Stato',
    'state-ready' => 'Aggiornata',
    'state-stale' => 'Schema non aggiornato',
    'state-missing' => 'Mancante',
    'filling' => 'Una ricostruzione sta riempiendo la tabella accanto.',
    'filled' => 'Una ricostruzione sta riempiendo la tabella accanto: :done di :total',
    'no-tables' => 'Il server non ha detto quali tabelle ha.',

    'queue' => 'Coda',
    'waiting' => 'In attesa di scrittura',
    'oldest' => 'La più vecchia dal',
    'queue-empty' => 'Ogni prodotto salvato è nell’indice.',

    'outdated' => 'L’indice non segue lo schema che il catalogo scrive ora',
    'outdated-text' => 'Nel frattempo il negozio risponde dalle vecchie tabelle, ma ciò che manca in esse — un nuovo filtro, i codici — non viene cercato. Una ricostruzione riempie nuove tabelle accanto a quelle attive e le scambia; su un catalogo grande sono minuti di carico, quindi scegli il momento.',
    'rebuild' => 'Ricostruisci',
    'rebuild-again' => 'Ricostruisci di nuovo',
    'rebuild-title' => 'Ricostruire l’indice di ricerca?',
    'rebuild-text' => 'Ogni prodotto viene riscritto in nuove tabelle, che poi sostituiscono quelle attive. Nel frattempo il negozio continua a funzionare sulle vecchie tabelle.',
    'rebuild-busy' => 'Una ricostruzione è già in corso.',
    'rebuild-locked' => 'È in corso un’altra ricostruzione, avviata dalla console. Avvia questa quando sarà finita.',
    'rebuild-console' => 'È in corso una ricostruzione avviata dalla console: il pulsante torna quando finisce.',
    'rebuild-queued' => 'In attesa del worker della coda',
    'rebuild-running' => 'Ricostruzione: :done di :total',
    'rebuild-done' => 'Ricostruiti: :done prodotti',
    'rebuild-failed' => 'La ricostruzione non è riuscita',
    'rebuild-stalled' => 'Da un quarto d’ora la ricostruzione non dà notizie: il worker della coda non è in esecuzione o si è fermato. Riavviala quando il worker sarà attivo.',
    'rebuild-started' => 'La ricostruzione è in coda.',
    'cancel' => 'Annulla',
];
