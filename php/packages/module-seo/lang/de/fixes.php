<?php

declare(strict_types=1);

return [
    'seo' => [
        'normalise-host' => [
            'title' => 'Auf den Hauptspiegel weiterleiten',
            'description' => 'Schaltet „Hauptspiegel“ in den SEO-Einstellungen ein: Der andere Name antwortet mit einer einzigen 301 auf diesen.',
        ],
        'normalise-https' => [
            'title' => 'Auf https weiterleiten',
            'description' => 'Schaltet „Immer https“ in den SEO-Einstellungen ein: Eine über http geöffnete Adresse antwortet mit einer einzigen 301 auf https.',
        ],
        'normalise-slashes' => [
            'title' => 'Doppelte Schrägstriche zusammenfassen',
            'description' => 'Schaltet „Doppelte Schrägstriche zusammenfassen“ in den SEO-Einstellungen ein.',
        ],
        'normalise-index' => [
            'title' => 'Index-Dateien abschneiden',
            'description' => 'Schaltet „Index-Dateien abschneiden“ in den SEO-Einstellungen ein: /index.php und /index.html leiten auf den Ordner weiter.',
        ],
        'normalise-trailing' => [
            'title' => 'Eine Form des Schrägstrichs am Ende',
            'description' => 'Stellt „Schrägstrich am Ende“ in den SEO-Einstellungen auf die Form ein, die die eigenen Links der Website verwenden.',
        ],
        'normalise-case' => [
            'title' => 'Auf Kleinschreibung weiterleiten',
            'description' => 'Schaltet „Kleinschreibung“ in den SEO-Einstellungen ein: /About antwortet mit einer einzigen 301 auf /about.',
        ],
        'collapse-chain' => [
            'title' => 'Die Kette auflösen',
            'description' => 'Richtet jede exakte Weiterleitung der Kette direkt auf die Adresse, bei der sie endet.',
        ],
        'robots-sitemap' => [
            'title' => 'Die Sitemap-Zeile hinzufügen',
            'description' => 'Schreibt die Zeile Sitemap: mit der Adresse der Sitemap in die robots.txt in den SEO-Einstellungen.',
        ],
    ],
];
