<?php

declare(strict_types=1);

return [
    'seo' => [
        'redirect_chain' => [
            'title' => 'Reindirizzamenti che portano ad altri reindirizzamenti',
            'found' => 'Un reindirizzamento nella tabella SEO punta a un indirizzo che viene a sua volta reindirizzato.',
            'why' => 'Ogni passaggio è un viaggio in più per il visitatore, e i motori di ricerca smettono di seguirli dopo qualche passaggio.',
            'fix' => 'Fai puntare il primo reindirizzamento direttamente all’ultimo indirizzo — il pulsante di correzione lo fa per ogni reindirizzamento esatto della catena.',
        ],
        'title_duplicate' => [
            'title' => 'Lo stesso titolo in più schede SEO',
            'found' => 'Più entità hanno lo stesso titolo scritto nella loro scheda SEO, nella stessa lingua.',
            'why' => 'Due pagine che si chiamano allo stesso modo competono tra loro nella ricerca, e nessuna sembra la risposta.',
            'fix' => 'Dai a ogni pagina un titolo che dica cosa c’è in essa e in nessun’altra.',
        ],
        'redirect_broken' => [
            'title' => 'Reindirizzamenti verso una pagina non funzionante',
            'found' => 'Un reindirizzamento esatto manda i visitatori a un indirizzo che durante la scansione ha risposto con un errore.',
            'why' => 'Il visitatore che ha seguito un vecchio link finisce su una pagina di errore, e il peso del vecchio indirizzo va perso.',
            'fix' => 'Fai puntare il reindirizzamento a una pagina esistente, oppure ripristina la pagina.',
        ],
        'rule_dead' => [
            'title' => 'Regole SEO per indirizzi che non esistono più',
            'found' => 'Una regola SEO esatta è scritta per un indirizzo che durante la scansione ha risposto 404 o 410.',
            'why' => 'Niente di dannoso, ma la regola non serve a nessuno e nasconde il fatto che la pagina per cui era stata scritta è sparita.',
            'fix' => 'Elimina la regola, oppure aggiungi un reindirizzamento da quell’indirizzo se la pagina è stata spostata.',
        ],
    ],
];
