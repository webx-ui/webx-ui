<?php

declare(strict_types=1);

return [
    'required' => 'Das Feld „:field“ darf nicht leer sein.',
    'name-long' => 'Der Name ist länger als :max Zeichen.',
    'code-long' => 'Der Code hat :bytes Bytes; die Grenze ist :max.',
    'consent' => 'Die Einwilligungskategorie ist eine von: :categories.',
    'mask' => 'Ein Pfad beginnt an der Wurzel, etwa „/blog/*“, ohne Adresse der Website und ohne „?“: [:mask] ist keiner.',
    'masks-many' => 'Nicht mehr als :max Pfade in einer Liste.',
    'locales' => 'Die Website erscheint nicht in :unknown. Sie hat: :known.',
    'boolean' => 'Das Feld „:field“ ist ja oder nein.',
    'list' => 'Erwartet wird eine Liste von Pfaden oder Sprachen.',
    'place-undeclared' => 'Der Platz „:place“ ist in config/webx-snippets.php nicht deklariert.',
    'place-orphaned' => 'Der Platz „:place“ ist nicht mehr deklariert, der Code würde nirgends ausgegeben: Verschieben Sie ihn oder schalten Sie ihn aus.',
    'document-tags' => 'Der Code enthält :tags: Fügen Sie nur ein, was der Dienst innerhalb der Seite verlangt.',
    'unknown' => 'Es gibt keinen Code mit der ID :ids.',
    'reorder' => 'Die Reihenfolge ist ein Platz und die Liste seiner Codes.',
];
