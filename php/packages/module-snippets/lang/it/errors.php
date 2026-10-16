<?php

declare(strict_types=1);

return [
    'required' => 'Il campo «:field» non può essere vuoto.',
    'name-long' => 'Il nome supera i :max caratteri.',
    'code-long' => 'Il codice pesa :bytes byte; il limite è :max.',
    'consent' => 'La categoria di consenso è una di: :categories.',
    'mask' => 'Un percorso parte dalla radice, come «/blog/*», senza l’indirizzo del sito né «?»: [:mask] non lo è.',
    'masks-many' => 'Non più di :max percorsi in un elenco.',
    'locales' => 'Il sito non è pubblicato in :unknown. Ha: :known.',
    'boolean' => 'Il campo «:field» è sì o no.',
    'list' => 'È atteso un elenco di percorsi o di lingue.',
    'place-undeclared' => 'La posizione «:place» non è dichiarata in config/webx-snippets.php.',
    'place-orphaned' => 'La posizione «:place» non è più dichiarata, il codice non verrebbe stampato da nessuna parte: spostalo o disattivalo.',
    'document-tags' => 'Il codice contiene :tags: incolla solo ciò che il servizio chiede di mettere dentro la pagina.',
    'unknown' => 'Non esiste un codice con id :ids.',
    'reorder' => 'L’ordine è una posizione e l’elenco dei suoi codici.',
];
