<?php

declare(strict_types=1);

return [
    'kind' => 'Un tipo è un blocco oppure un componente.',
    'kind-in-use' => 'Il blocco si trova su delle pagine e non può diventare un componente. Pagine: :count.',
    'unknown-call' => '«:type» non è un tipo di blocco: la chiamata non stampa nulla sul sito.',
    'dynamic-call' => 'Il tipo chiamato non è scritto per esteso: pubblicarlo non controllerà questo template.',
    'delete-used-by' => 'Altri tipi chiamano questo, e i loro template lascerebbero un vuoto. Rimuovi prima le chiamate.',
    'delete-used-by-one' => 'Chiamato da «:title» (:slug)',
    'publish-cycle' => 'I tipi si chiamano a vicenda in cerchio: :path.',
    'publish-breaks-parent' => 'Rompe «:parent» sul suo esempio: :reason',
    'publish-breaks-parent-on' => 'Rompe «:parent» su «:entity»: :reason',
    'publish-breaks-declared' => 'Rompe il punto da cui lo chiama il modulo :module: :reason',
    'customise-exists' => 'Esiste già un tipo con questo identificatore.',
    'customised-from' => 'Da :view',
];
