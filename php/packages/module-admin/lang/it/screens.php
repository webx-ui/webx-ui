<?php

declare(strict_types=1);

return [
    'unknown' => 'Non esiste una schermata chiamata :name.',
    'row' => 'Riga :number: :message',
    'row-shape' => 'Questa riga non è un insieme di campi.',
    'row-unknown-key' => 'Questa riga non ha il campo «:key». Una riga accetta: :keys.',
    'icon-unknown' => 'Questo set non ha l’icona «:name». Il set contiene: :names.',
    'icon-source-unknown' => 'Questo sito non ha il set di icone «:source».',
    'embed-source-unknown' => 'Questo campo accetta i frame di «:source», e il sito non ha questo servizio.',
    // The card a module's screen keeps for the fields a project patches in (`project-fields`).
    'project-fields' => 'Altro',
    // The words of a list of records on a described screen; the core has English only.
    'repeater' => [
        'add' => 'Aggiungi',
        'remove' => 'Rimuovi',
        'remove-question' => 'Rimuovere questa riga?',
        'cancel' => 'Annulla',
        'reorder' => 'Riordina',
        'empty' => 'Ancora niente',
    ],
    'not-a-language-map' => ':field è tradotto: invia un valore per lingua, { "en": … }, non un solo elenco.',
];
