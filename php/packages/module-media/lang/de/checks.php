<?php

declare(strict_types=1);

return [
    'media' => [
        'missing_file' => [
            'title' => 'Dateien der Bibliothek fehlen auf dem Datenträger',
            'found' => 'Die Medienbibliothek listet eine Datei, die der Datenträger nicht hat.',
            'why' => 'Jede Seite und jedes Feld, die sie verwenden, zeigen ein defektes Bild oder einen toten Download-Link.',
            'fix' => 'Laden Sie die Datei in der Medienbibliothek erneut hoch oder kopieren Sie den Storage-Ordner von dort, woher die Website stammt.',
        ],
        'heavy' => [
            'title' => 'Bilder, die für eine Seite zu schwer sind',
            'found' => 'Bilder in der Medienbibliothek wiegen mehr als das Limit.',
            'why' => 'Eine Seite, die eines zeigt, lädt auf dem Handy langsam, und Suchmaschinen ranken langsame Seiten niedriger.',
            'fix' => 'Ersetzen Sie sie durch kleinere Versionen: Ein Foto für eine Seite muss selten breiter als 2000 Pixel oder schwerer als einige hundert Kilobyte sein.',
        ],
        'orphan_thumbs' => [
            'title' => 'Vorschauen gelöschter Dateien',
            'found' => 'Auf dem Datenträger liegen Vorschauordner von Dateien, die die Mediathek nicht mehr hat.',
            'why' => 'Sie belegen Platz, und nichts wird sie je anzeigen.',
            'fix' => 'Löschen Sie sie mit der Schaltfläche hier oder mit php artisan webx:media:prune-thumbs.',
        ],
    ],
];
