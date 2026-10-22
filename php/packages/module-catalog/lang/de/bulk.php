<?php

declare(strict_types=1);

return [
    'actions' => [
        'publish' => 'Veröffentlichen',
        'unpublish' => 'Vom Netz nehmen',
        'set-category' => 'Hauptkategorie festlegen',
        'add-category' => 'Kategorie hinzufügen',
        'remove-category' => 'Kategorie entfernen',
        'delete' => 'Löschen',
        'restore' => 'Wiederherstellen',
    ],
    'params' => [
        'category' => 'Kategorie',
    ],
    'errors' => [
        'selection' => 'Wählen Sie die Produkte: eine Liste von IDs oder die Abfrage der Liste.',
        'too-many' => 'Zu viele IDs auf einmal; senden Sie stattdessen die Abfrage der Liste.',
        'unknown-action' => 'Eine solche Sammelaktion gibt es nicht. Bekannt: :known',
        'forbidden' => 'Diese Aktion braucht die Berechtigung :permission.',
        'gone' => 'Das Produkt ist nicht mehr dort, wo der Lauf es gefunden hat.',
    ],
];
