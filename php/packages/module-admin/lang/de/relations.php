<?php

declare(strict_types=1);

/*
 * A field that points at records of another section (`wx-relations`), and the same choice as a
 * block's filter ("only related to" in `wx-collection`). The keys are the panel's: the English
 * here is what `messages.ts` carries.
 */

return [
    'field-add' => 'Hinzufügen…',
    'field-searching' => 'Suche läuft…',
    'field-nothing' => 'Nichts gefunden.',
    'field-empty' => 'Noch nichts gewählt.',
    'field-remove' => 'Entfernen',
    'field-drag' => 'Ziehen, um die Reihenfolge zu ändern',
    'field-hidden' => 'Nicht auf der Website',
    'field-trashed' => 'Im Papierkorb',
    'field-missing' => 'Nicht gefunden',
    'field-full' => 'Es können höchstens :max gewählt werden.',
    'field-forbidden' => 'Sie können diese Einträge nicht sehen, deshalb lässt sich die Auswahl hier nicht ändern.',
    'collection-related' => 'Nur verknüpft mit',
    'collection-related-to' => 'Nur verknüpft mit „:target“',
    'collection-related-type' => 'Welcher Bereich',
    'collection-related-any' => 'Nicht eingegrenzt: alle Einträge, verknüpft oder nicht.',
    'collection-related-current' => 'Dem Eintrag der Seite, auf der er steht',
    'collection-related-current-hint' => 'Auf der Seite eines Eintrags aus „:target“ zeigt der Block, was damit verknüpft ist; auf jeder anderen Seite nichts.',
];
