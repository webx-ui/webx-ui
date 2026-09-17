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
    'signed-out' => 'Sie sind abgemeldet. Melden Sie sich erneut an und versuchen Sie es noch einmal.',
    'forbidden' => 'Dazu haben Sie keine Berechtigung.',
    'gone' => 'Das gibt es nicht mehr — jemand hat es womöglich gelöscht.',
    'conflict' => 'Jemand hat das geändert, während Sie daran gearbeitet haben.',
    'throttled' => 'Zu viele Versuche. Versuchen Sie es gleich noch einmal.',
    'throttled-in' => 'Zu viele Versuche. Versuchen Sie es in :seconds Sekunden noch einmal.',
    'server' => 'Der Server konnte das nicht ausführen. Versuchen Sie es gleich noch einmal.',
    'offline' => 'Der Server hat nicht geantwortet. Prüfen Sie die Verbindung und versuchen Sie es erneut.',
    'unknown' => 'Das hat nicht geklappt.',
];
