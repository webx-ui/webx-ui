<?php

declare(strict_types=1);

return [
    'on' => 'Attivo',
    'normalise-note' => 'Funziona per le richieste che il web server passa al sito. Se il risultato resta dopo la prossima esecuzione, è il web server a rispondere da solo a quell’indirizzo: configura lì il reindirizzamento.',
    'robots-file-note' => 'Il sito ha un file public/robots.txt. Il web server lo serve prima di interpellare il sito, quindi eliminalo perché l’impostazione abbia effetto.',
    'redirect-chain' => 'Passaggi prima della pagina: :steps',
    'title-duplicate' => '«:title» — in :count schede',
    'redirect-broken' => ':target risponde :status',
    'rule-dead' => 'L’indirizzo risponde :status',
];
