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
    'signed-out' => 'Votre session est terminée. Reconnectez-vous et réessayez.',
    'forbidden' => 'Vous n’avez pas le droit de faire cela.',
    'gone' => 'Ce n’est plus là — quelqu’un l’a peut-être supprimé.',
    'conflict' => 'Quelqu’un l’a modifié pendant que vous y travailliez.',
    'throttled' => 'Trop de tentatives. Réessayez dans un instant.',
    'throttled-in' => 'Trop de tentatives. Réessayez dans :seconds secondes.',
    'server' => 'Le serveur n’a pas pu le faire. Réessayez dans un instant.',
    'offline' => 'Le serveur n’a pas répondu. Vérifiez la connexion et réessayez.',
    'unknown' => 'Cela n’a pas marché.',
];
