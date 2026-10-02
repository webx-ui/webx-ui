<?php

declare(strict_types=1);

return [
    'tab' => 'Audit',
    'base-url' => 'Indirizzo da verificare',
    'base-url-help' => 'Vuoto significa APP_URL. L’audit chiede al sito le sue pagine a questo indirizzo.',
    'resolve-to' => 'Connetti a',
    'resolve-to-help' => 'Un indirizzo IP o un host a cui aprire la connessione, mantenendo il nome pubblico nella richiesta. Vuoto significa ciò che risponde il DNS. Per Docker e server dietro NAT.',
    'other-hosts' => 'Altri indirizzi di questo sito',
    'other-hosts-help' => 'Ambienti di sviluppo, di staging e vecchi domini, uno per riga. Un link a uno di essi è un errore ovunque venga trovato.',
];
