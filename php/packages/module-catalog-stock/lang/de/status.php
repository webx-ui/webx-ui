<?php

declare(strict_types=1);

return [
    'title' => 'Name',
    'code' => 'Code',
    'code-help' => 'Lateinische Buchstaben, Ziffern und Bindestriche: der Status in der Filteradresse (stock_in-stock) und in Vorlagen. Leer gelassen, entsteht er aus dem Namen.',
    'color' => 'Farbton',
    'color-help' => 'Die Website färbt die Statuszeile in diesem Farbton; das Panel zeigt ein Tag im selben Farbton.',
    'tones' => [
        'neutral' => 'Neutral',
        'primary' => 'Primär',
        'success' => 'Erfolg',
        'warning' => 'Warnung',
        'danger' => 'Gefahr',
        'info' => 'Info',
    ],
    'purchasable' => 'Kaufbar',
    'purchasable-help' => 'Aus: Ein Produkt in diesem Status kann nicht gekauft werden, und sein Name steht auf der Website statt des Kaufen-Buttons.',
    'default' => 'Standard',
    'default-help' => 'Der Status jedes Produkts, für das niemand einen Status gesetzt hat, und eines neuen Produkts. Um ihn zu ändern, machen Sie einen anderen Status zum Standard.',
    'filterable' => 'Im Filter',
    'filterable-help' => 'Aus: Der Status ist keine Auswahl im Katalogfilter.',
    'new' => 'Neuer Status',
    'empty' => 'Noch keine Lagerstatus.',
    'empty-help' => 'Ein Status sagt, ob ein Produkt vorrätig ist und ob es gekauft werden kann.',
    'order' => 'Die Reihenfolge hier ist die Reihenfolge des Filters',
    'hidden' => 'Nicht im Filter',
    'count' => 'Produkte: :count',
    'show-items' => 'Seine Produkte zeigen',
    'delete-blocked' => 'Solange Produkte diesen Status haben, kann er nicht weg — verschieben Sie sie zuerst in einen anderen Status.',
    'delete-text' => 'Er wandert in den Papierkorb und verschwindet aus dem Filter.',
    'deleted' => 'Der Status liegt im Papierkorb.',
    'saved' => 'Der Status ist gespeichert.',
];
