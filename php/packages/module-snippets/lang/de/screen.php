<?php

declare(strict_types=1);

return [
    'name' => 'Name',
    'name-help' => 'Nur für Administratoren, erscheint nie auf der Website.',
    'place' => 'Platz',
    'place-help' => 'Wo auf der Seite der Code ausgegeben wird. Eigene Plätze werden in config/webx-snippets.php deklariert.',
    'code' => 'Code',
    'code-help' => 'Fügen Sie genau das ein, was der Dienst geliefert hat, ohne <html>, <head> und <body>. document.write funktioniert nicht in einem Code, der auf Einwilligung wartet.',
    'settings' => 'Einstellungen',
    'enabled' => 'Aktiv',
    'enabled-help' => 'Ein aktiver Code ist sofort nach dem Speichern auf der Website.',
    'consent' => 'Cookie-Einwilligung',
    'consent-help' => 'Alles außer „Notwendig“ wird erst ausgegeben, wenn der Besucher dieser Kategorie zustimmt.',
    'on-stage' => 'Auch auf Dev und Stage',
    'on-stage-help' => 'Aus: Der Code wird nur in der Produktion ausgegeben, eine Kopie der Website zählt keine Besuche.',
    'coverage' => 'Wo er ausgegeben wird',
    'only-paths' => 'Nur auf',
    'only-paths-help' => 'Pfade ohne Sprache, * für beliebiges: /, /blog/*. Leer heißt die ganze Website.',
    'except-paths' => 'Außer',
    'except-paths-help' => 'Pfade, auf denen der Code nie ausgegeben wird, etwa /blog/drafts/*.',
    'locales' => 'Sprachen',
    'locales-help' => 'Nichts angehakt heißt alle Sprachen.',
    'history' => 'Verlauf',
    'position' => 'Reihenfolge',
];
