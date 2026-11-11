<?php

declare(strict_types=1);

return [
    'unknown' => 'Es gibt keinen Bildschirm namens :name.',
    'row' => 'Zeile :number: :message',
    'row-shape' => 'Diese Zeile ist kein Satz von Feldern.',
    'row-unknown-key' => 'Diese Zeile hat kein Feld „:key“. Eine Zeile nimmt: :keys.',
    'icon-unknown' => 'In diesem Satz gibt es kein Symbol „:name“. Der Satz enthält: :names.',
    'icon-source-unknown' => 'Auf dieser Website gibt es keinen Symbolsatz „:source“.',
    // The card a module's screen keeps for the fields a project patches in (`project-fields`).
    'project-fields' => 'Weiteres',
    // The words of a list of records on a described screen; the core has English only.
    'repeater' => [
        'add' => 'Hinzufügen',
        'remove' => 'Entfernen',
        'remove-question' => 'Diese Zeile entfernen?',
        'cancel' => 'Abbrechen',
        'reorder' => 'Verschieben',
        'empty' => 'Noch nichts hier',
    ],
    'not-a-language-map' => ':field wird übersetzt: Senden Sie einen Wert je Sprache, { "en": … }, nicht eine Liste.',
];
