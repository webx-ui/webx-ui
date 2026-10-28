<?php

declare(strict_types=1);

return [
    'hello' => 'Ciao, :name!',
    'ignore' => 'Se non l’hai richiesto, ignora questo messaggio.',
    'verify' => [
        'subject' => 'Conferma la tua e-mail su :site',
        'text' => 'Conferma che questo è il tuo indirizzo su :site.',
        'button' => 'Conferma l’e-mail',
        'expires' => 'Il link vale :hours ore.',
    ],
    'reset' => [
        'subject' => 'Una nuova password su :site',
        'text' => 'Qualcuno ha chiesto di recuperare la password del tuo account su :site.',
        'button' => 'Imposta una nuova password',
        'expires' => 'Il link vale :minutes minuti e una sola volta.',
    ],
    'password-changed' => [
        'subject' => 'La tua password su :site è stata cambiata',
        'text' => 'La password del tuo account su :site è stata appena cambiata.',
        'not-you' => 'Se non sei stato tu, recupera subito la password.',
        'button' => 'Recupera la password',
    ],
    'email-change' => [
        'subject' => 'Conferma il nuovo indirizzo su :site',
        'text' => 'Apri il link per accedere d’ora in poi a :site con :email.',
        'button' => 'Conferma l’indirizzo',
        'expires' => 'Il link vale :minutes minuti.',
    ],
    'email-changed' => [
        'subject' => 'Il tuo indirizzo di accesso su :site è stato cambiato',
        'text' => 'Il tuo account su :site ora accede con :email.',
        'not-you' => 'Se non sei stato tu, recupera la password e contattaci.',
        'button' => 'Recupera la password',
    ],
];
