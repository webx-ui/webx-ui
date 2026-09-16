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
];
