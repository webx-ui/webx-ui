<?php

declare(strict_types=1);

return [
    'no-marker' => 'Nessun data-wx-block sulla radice: lo script non partirà e il pannello non potrà evidenziare il blocco nell’anteprima.',
    'stray-selectors' => 'Selettori fuori dal prefisso del blocco .b-:slug: :selectors',
    'bare-selectors' => 'I selettori di elemento raggiungono tutto il sito: :selectors',
    'media-query' => '@media misura la finestra. Un blocco si dimensiona sul suo contenitore: usa @container.',
    'variables-missing' => 'Il template usa :variables, che lo schema non dichiara. La pubblicazione sarà rifiutata.',
    'ok-marker' => 'La radice porta data-wx-block.',
    'ok-prefix' => 'Ogni selettore inizia con .b-:slug.',
    'ok-bare' => 'Nessun selettore di elemento nudo.',
    'ok-container' => 'La larghezza è decisa dalle container query.',
    'ok-variables' => 'Ogni variabile del template è un campo dello schema.',
    'blocks' => [
        'stray_values' => [
            'title' => 'Valori di blocco per campi che il tipo non ha',
            'found' => 'Alcuni blocchi hanno valori di campi che il loro tipo non definisce, rimasti da un’importazione o da un campo tolto dal tipo.',
            'why' => 'Il visitatore non li vede, ma stanno nei dati dell’editor e in ciò che legge un agente, e riemergono come etichetta sbagliata del blocco.',
            'fix' => 'Toglieteli con la correzione o per tutto il sito con php artisan webx:blocks:prune. Un blocco di un tipo che non esiste più non viene toccato. Gli elementi di un ripetitore sono confrontati con i suoi campi.',
        ],
    ],
    'syntax' => 'Il modello non si compila: :reason. La pubblicazione sarà rifiutata.',
    'unknown-field-type' => 'Campi di un tipo che il sito non conosce: :fields. Il modulo mostra un avviso al loro posto e nessuno ne controlla i valori.',
    'field-id' => 'L\'id di un campo è fatto di lettere, cifre, _ e -, e inizia con una lettera: :ids.',
    'marker-slug' => 'La radice è marcata data-wx-block=":marker", ma l’identificatore è «:slug»: lo script e il pannello trovano il blocco con l’identificatore esatto. La pubblicazione sarà rifiutata.',
];
