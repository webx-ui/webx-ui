<?php

declare(strict_types=1);

return [
    'media' => [
        'missing_file' => [
            'title' => 'File della libreria mancanti sul disco',
            'found' => 'La libreria multimediale elenca un file che il disco non ha.',
            'why' => 'Ogni pagina e ogni campo che lo usano mostrano un’immagine rotta o un link di download morto.',
            'fix' => 'Carica di nuovo il file nella libreria multimediale, oppure copia la cartella storage da dove proviene il sito.',
        ],
        'heavy' => [
            'title' => 'Immagini troppo pesanti per una pagina',
            'found' => 'Alcune immagini della libreria multimediale pesano più del limite.',
            'why' => 'Una pagina che ne mostra una si carica lentamente su un telefono, e i motori di ricerca posizionano più in basso le pagine lente.',
            'fix' => 'Sostituiscile con versioni più piccole: una foto per una pagina raramente deve superare i 2000 pixel di larghezza o qualche centinaio di kilobyte.',
        ],
        'orphan_thumbs' => [
            'title' => 'Anteprime di file eliminati',
            'found' => 'Il disco conserva cartelle di anteprime di file che la libreria media non ha più.',
            'why' => 'Occupano spazio e niente le mostrerà mai.',
            'fix' => 'Eliminale con il pulsante qui o con php artisan webx:media:prune-thumbs.',
        ],
    ],
];
