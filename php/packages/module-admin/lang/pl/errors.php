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
    'signed-out' => 'Sesja się zakończyła. Zaloguj się ponownie i spróbuj jeszcze raz.',
    'forbidden' => 'Nie masz do tego uprawnień.',
    'gone' => 'Tego już nie ma — ktoś mógł to usunąć.',
    'conflict' => 'Ktoś to zmienił, kiedy nad tym pracowałeś.',
    'throttled' => 'Za dużo prób. Spróbuj za chwilę.',
    'throttled-in' => 'Za dużo prób. Spróbuj za :seconds s.',
    'server' => 'Serwer nie dał rady. Spróbuj za chwilę.',
    'offline' => 'Serwer nie odpowiedział. Sprawdź połączenie i spróbuj ponownie.',
    'unknown' => 'Nie udało się.',
];
