<?php

declare(strict_types=1);

return [
    'routing' => [
        'orphan' => [
            'title' => 'Indirizzi di record eliminati',
            'found' => 'Una riga del registro degli indirizzi punta a un record che non esiste più.',
            'why' => 'L’indirizzo risponde 404 ma conserva il suo nome, quindi un nuovo record non può prenderlo.',
            'fix' => 'Esegui php artisan webx:routes:rebuild, oppure ripristina il record se è stato eliminato per errore.',
        ],
        'alias_broken' => [
            'title' => 'Vecchi indirizzi che non portano da nessuna parte',
            'found' => 'Un alias — il vecchio indirizzo conservato dopo il cambio di uno slug — non porta ad alcun indirizzo, oppure porta a un altro alias.',
            'why' => 'Un visitatore con un vecchio link riceve un 404, oppure un reindirizzamento a un reindirizzamento.',
            'fix' => 'Esegui php artisan webx:routes:rebuild, oppure elimina l’alias nella scheda «Automatici» della sezione SEO.',
        ],
        'shadowed' => [
            'title' => 'Indirizzi a cui risponde l’applicazione stessa',
            'found' => 'Una route dell’applicazione ha lo stesso indirizzo di un record del registro.',
            'why' => 'Il record non viene mai mostrato: risponde prima la route dell’applicazione.',
            'fix' => 'Cambia lo slug del record, oppure la route dell’applicazione.',
        ],
        'no_address' => [
            'title' => 'Record senza indirizzo',
            'found' => 'Un record che dovrebbe avere un indirizzo in una lingua non ne ha.',
            'why' => 'La pagina non può essere aperta, non è nella sitemap e non si può linkare.',
            'fix' => 'Salva di nuovo il record, oppure esegui php artisan webx:routes:rebuild.',
        ],
        'unknown_type' => [
            'title' => 'Indirizzi di un modulo non installato',
            'found' => 'Il registro contiene indirizzi di un tipo che nessun modulo installato conosce.',
            'why' => 'Non rispondono a nulla e continuano a tenere i loro nomi contro ogni nuovo record.',
            'fix' => 'Rimuovi quelle righe, oppure installa di nuovo il modulo.',
        ],
    ],
];
