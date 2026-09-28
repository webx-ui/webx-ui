<?php

declare(strict_types=1);

// The regions of the layout (the regions spec): the words a declaration may point at with
// `trans::webx-blocks::regions.header`, and what the server says about a region.
return [
    'header' => 'Intestazione',
    'footer' => 'Piè di pagina',
    'usage' => 'Zona «:title»',
    'preview-failed' => 'Un blocco di questa zona non funziona. Sul sito l’intera zona mostrerà al suo posto il markup del codice.',
    'not-in-registry' => ':path non è una pagina del registro degli indirizzi del sito, quindi la zona è mostrata su una pagina vuota del layout.',
    'too-many' => 'La zona contiene al massimo :max blocchi.',
    'not-allowed' => 'Il blocco «:type» non può essere messo in questa zona.',
    'refused' => 'La zona non accetta questi blocchi.',
    'conflict' => 'La zona è cambiata da quando l’hai aperta. Ricaricala per vedere le modifiche.',
    'failed-block' => 'Il blocco «:type» (:key) non funziona: :reason',
    'not-published' => 'Non pubblicato: un blocco della bozza non si disegna.',
    'nothing-to-publish' => 'La zona non è mai stata salvata: non c’è nulla da pubblicare.',
    'no-version' => 'La zona non ha la versione :number.',
    'no-fallback' => 'Il layout non ha ancora indicato una vista per questa zona, oppure la vista non c’è più.',
    'adopt-taken' => 'Esiste già un tipo di blocco «:slug».',
    'adopt-failed' => 'Il markup non è potuto diventare un tipo di blocco.',
    'adopt-forbidden' => 'Spostare il markup in un tipo di blocco richiede il permesso di modificare i tipi di blocco.',
];
