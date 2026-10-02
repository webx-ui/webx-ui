<?php

declare(strict_types=1);

return [
    'routing' => [
        'orphan' => [
            'title' => 'Adressen gelöschter Datensätze',
            'found' => 'Eine Zeile des Adressregisters zeigt auf einen Datensatz, den es nicht mehr gibt.',
            'why' => 'Die Adresse antwortet 404, hält aber weiter ihren Namen, sodass ihn ein neuer Datensatz nicht übernehmen kann.',
            'fix' => 'Führen Sie php artisan webx:routes:rebuild aus oder stellen Sie den Datensatz wieder her, falls er versehentlich gelöscht wurde.',
        ],
        'alias_broken' => [
            'title' => 'Alte Adressen, die ins Leere führen',
            'found' => 'Ein Alias — die alte Adresse, die nach einer Slug-Änderung erhalten bleibt — führt zu keiner Adresse oder zu einem anderen Alias.',
            'why' => 'Ein Besucher mit einem alten Link bekommt ein 404 oder eine Weiterleitung auf eine Weiterleitung.',
            'fix' => 'Führen Sie php artisan webx:routes:rebuild aus oder löschen Sie den Alias im Tab „Automatisch“ des Bereichs SEO.',
        ],
        'shadowed' => [
            'title' => 'Adressen, die die Anwendung selbst beantwortet',
            'found' => 'Eine Route der Anwendung hat dieselbe Adresse wie ein Datensatz des Registers.',
            'why' => 'Der Datensatz wird nie angezeigt: Die Route der Anwendung antwortet zuerst.',
            'fix' => 'Ändern Sie den Slug des Datensatzes oder die Route der Anwendung.',
        ],
        'no_address' => [
            'title' => 'Datensätze ohne Adresse',
            'found' => 'Ein Datensatz, der in einer Sprache eine Adresse haben sollte, hat keine.',
            'why' => 'Die Seite lässt sich nicht öffnen, steht nicht in der Sitemap und kann nicht verlinkt werden.',
            'fix' => 'Speichern Sie den Datensatz erneut oder führen Sie php artisan webx:routes:rebuild aus.',
        ],
        'unknown_type' => [
            'title' => 'Adressen eines nicht installierten Moduls',
            'found' => 'Das Register enthält Adressen eines Typs, den kein installiertes Modul kennt.',
            'why' => 'Sie antworten auf nichts und halten trotzdem ihre Namen gegen jeden neuen Datensatz.',
            'fix' => 'Entfernen Sie diese Zeilen oder installieren Sie das Modul erneut.',
        ],
    ],
];
