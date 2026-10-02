<?php

declare(strict_types=1);

return [
    'seo' => [
        'redirect_chain' => [
            'title' => 'Weiterleitungen, die auf Weiterleitungen führen',
            'found' => 'Eine Weiterleitung in der SEO-Tabelle zeigt auf eine Adresse, die erneut weitergeleitet wird.',
            'why' => 'Jeder Sprung ist ein weiterer Umweg für den Besucher, und Suchmaschinen hören nach wenigen auf, ihnen zu folgen.',
            'fix' => 'Richten Sie die erste Weiterleitung direkt auf die letzte Adresse — die Korrektur-Schaltfläche erledigt das für jede exakte Weiterleitung der Kette.',
        ],
        'title_duplicate' => [
            'title' => 'Derselbe Titel in mehreren SEO-Karten',
            'found' => 'Mehrere Entitäten haben in ihrer SEO-Karte denselben Titel, in derselben Sprache.',
            'why' => 'Zwei Seiten, die sich gleich nennen, konkurrieren in der Suche miteinander, und keine wirkt wie die Antwort.',
            'fix' => 'Geben Sie jeder Seite einen Titel, der sagt, was auf ihr steht, und nirgends sonst.',
        ],
        'redirect_broken' => [
            'title' => 'Weiterleitungen auf eine defekte Seite',
            'found' => 'Eine exakte Weiterleitung schickt Besucher zu einer Adresse, die beim Crawl mit einem Fehler geantwortet hat.',
            'why' => 'Der Besucher, der einem alten Link gefolgt ist, landet auf einer Fehlerseite, und das Gewicht der alten Adresse geht verloren.',
            'fix' => 'Richten Sie die Weiterleitung auf eine existierende Seite oder stellen Sie die Seite wieder her.',
        ],
        'rule_dead' => [
            'title' => 'SEO-Regeln für Adressen, die es nicht mehr gibt',
            'found' => 'Eine exakte SEO-Regel ist für eine Adresse geschrieben, die beim Crawl mit 404 oder 410 geantwortet hat.',
            'why' => 'Nichts Schädliches, aber die Regel ist für niemanden da und verdeckt, dass die Seite, für die sie geschrieben wurde, verschwunden ist.',
            'fix' => 'Löschen Sie die Regel oder legen Sie eine Weiterleitung von dieser Adresse an, falls die Seite umgezogen ist.',
        ],
    ],
];
