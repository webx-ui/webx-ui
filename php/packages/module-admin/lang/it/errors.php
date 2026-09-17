<?php

declare(strict_types=1);

/*
 * How a request fails, said in the panel's words.
 *
 * The browser half decides which status gets which line (`errors.ts`): a 422 is a refusal a
 * module wrote to be read, and everything else — a 404, a 403, a server that fell over — carries
 * text nobody wrote for an editor. These are what it says instead.
 */

return [
    'signed-out' => 'La sessione è terminata. Accedi di nuovo e riprova.',
    'forbidden' => 'Non hai i permessi per farlo.',
    'gone' => 'Non c’è più: qualcuno potrebbe averlo eliminato.',
    'conflict' => 'Qualcuno l’ha cambiato mentre ci lavoravi.',
    'throttled' => 'Troppi tentativi. Riprova tra poco.',
    'throttled-in' => 'Troppi tentativi. Riprova tra :seconds secondi.',
    'server' => 'Il server non è riuscito a farlo. Riprova tra poco.',
    'offline' => 'Il server non ha risposto. Controlla la connessione e riprova.',
    'unknown' => 'Non ha funzionato.',
];
