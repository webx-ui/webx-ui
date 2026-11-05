<?php

declare(strict_types=1);

return [
    'currency' => 'Un feed usa la valuta del sito: :currency.',
    'currency-unset' => 'Il sito non ha una valuta: imposta WEBX_CATALOG_CURRENCY.',
    'filter-facet' => 'Il filtro del feed cita [:facet], che il sito non ha.',
    'source-property' => 'Il feed prende l’MPN dalla proprietà [:property], che il sito non ha.',
    'no-item' => 'Il feed non ha un modello di prodotto.',
    'template' => 'Il modello non è leggibile: :reason',
    'too-large' => 'Il file supera la dimensione consentita di un feed.',
    'stale' => 'La generazione ha smesso di avanzare ed è stata chiusa.',
    'file-xml' => 'Il file non è un XML valido, riga :line: :reason',
    'file-json' => 'Intestazione e piè di pagina non formano un JSON valido: :reason',
    'file-json-item' => 'Il prodotto :product non forma un JSON valido: :reason',
    'file-csv' => 'La riga :line del file ha :found campi invece di :expected.',
    'sample-json' => 'Il campione non è JSON valido, riga :line: :reason',
    'sample-place' => 'Parte «:part», riga :line: :fragment',
    'unknown-variable' => '{{:name}} non esiste nella parte «:part».',
    'no-products' => 'Nessun prodotto è ancora adatto al feed: sono stati controllati solo intestazione e piè di pagina.',
];
