<?php

declare(strict_types=1);

return [
    'menu' => [
        'broken' => [
            'title' => 'Menüpunkte, die auf einen Fehler führen',
            'found' => 'Ein Menüpunkt führt zu einer Seite, die mit einem Fehler antwortet, oder zu einem Datensatz, der keine Adresse mehr hat.',
            'why' => 'Ein Menü steht auf jeder Seite: Ein defekter Punkt ist überall ein defekter Link und das Erste, worauf ein Besucher klickt.',
            'fix' => 'Richten Sie den Punkt auf eine existierende Seite oder entfernen Sie ihn.',
        ],
        'redirect' => [
            'title' => 'Menüpunkte, die auf eine Weiterleitung führen',
            'found' => 'Ein Menüpunkt führt zu einer Adresse, die anderswohin weiterleitet.',
            'why' => 'Jeder Klick kostet einen zusätzlichen Umweg, auf jeder Seite, auf der das Menü ausgegeben wird.',
            'fix' => 'Richten Sie den Punkt auf die Adresse, bei der er landet.',
        ],
    ],
];
