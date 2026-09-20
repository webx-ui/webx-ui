<?php

declare(strict_types=1);

return [
    'new' => 'Nuovo tag',
    'new-title' => 'Nuovo tag',
    'field-title' => 'Nome',
    'field-slug' => 'Indirizzo',
    'field-slug-help' => 'Lasciato vuoto, viene ricavato dal nome.',
    'create' => 'Crea',
    'cancel' => 'Annulla',

    'search' => 'Cerca per parola o indirizzo',
    'empty' => 'Ancora nessun tag.',
    'no-address' => 'Nessun indirizzo in questa lingua',

    'column-title' => 'Nome',
    'column-address' => 'Indirizzo',
    'column-articles' => 'Articoli',
    'column-indexing' => 'Indicizzazione',

    'view-all' => 'Tutti',
    'view-empty' => 'Senza articoli',
    'view-noindex' => 'Non indicizzati',
    'sort-articles' => 'Più usati',
    'sort-name' => 'Dalla A alla Z',

    'indexing-open' => 'indicizzato',
    'indexing-rule' => 'indicizzato — regola SEO',
    'indexing-noindex' => 'noindex',
    'indexing-rule-help' => 'Una regola nella sezione SEO copre questo indirizzo, quindi la pagina è nell’indice comunque sia impostato il tag.',

    'rename' => 'Rinomina',
    'renamed' => 'Tag rinominato.',
    'index' => 'Indicizza',
    'noindex' => 'Togli dall’indice',
    'indexed' => 'La pagina del tag è nell’indice.',
    'kept-out' => 'La pagina del tag è fuori dall’indice.',
    'indexed-many' => ':count pagine di tag sono nell’indice.',
    'kept-out-many' => ':count pagine di tag sono fuori dall’indice.',
    'open-on-site' => 'Apri sul sito',
    'show-articles' => 'Mostra i suoi articoli',

    'delete' => 'Elimina',
    'delete-title' => 'Eliminare «:title»?',
    'delete-text' => 'I tag non hanno cestino: sparisce per sempre.',
    'delete-text-used' => 'È su :count articoli e verrà tolto da tutti. I tag non hanno cestino: sparisce per sempre.',
    'deleted' => 'Tag eliminato.',
    'delete-many-title' => 'Eliminare :count tag?',
    'delete-many-text' => 'I tag non hanno cestino: spariscono per sempre.',
    'delete-many-text-used' => 'Insieme stanno su :count articoli e verranno tolti da tutti. Per questo non c’è cestino.',
    'deleted-many' => ':count tag eliminati.',

    'selected' => ':count selezionati',
    'clear' => 'Annulla la selezione',
    'merge' => 'Unisci in uno',
    'merged' => ':count articoli portano ora «:tag».',
    'merge-title' => 'Unisci i tag',
    'merge-keep' => 'Quale tag resta',
    'merge-redirect' => 'Reindirizza i vecchi indirizzi',
    'merge-redirect-help' => ':addresses risponderanno 301.',
    'merge-redirect-none' => 'Questi tag non hanno un indirizzo in questa lingua, quindi non c’è nulla da reindirizzare.',
    'merge-warning' => ':count articoli porteranno «:tag» al suo posto. :merged tag verranno eliminati, e non si torna indietro.',
    'merge-confirm' => 'Unisci',
];
