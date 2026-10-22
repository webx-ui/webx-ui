<?php

declare(strict_types=1);

return [
    'catalog' => [
        'category_description' => [
            'title' => 'Kategorien ohne Beschreibung',
            'found' => 'Veröffentlichte Kategorien haben keine Beschreibung.',
            'why' => 'Eine Kategorieseite, die nur eine Produktliste enthält, sagt einer Suchmaschine wenig und gilt schnell als dünne Seite.',
            'fix' => 'Schreiben Sie ein paar Sätze dazu, was die Kategorie enthält und wie man darin auswählt.',
        ],
        'product_image' => [
            'title' => 'Produkte ohne Bild',
            'found' => 'Veröffentlichte Produkte haben kein Bild.',
            'why' => 'Ein Produkt ohne Bild verkauft sich schlechter, wirkt in der Liste unfertig und hat beim Teilen nichts zu zeigen.',
            'fix' => 'Fügen Sie jedem Produkt mindestens ein Bild hinzu, oder nehmen Sie die noch nicht fertigen vom Netz.',
        ],
        'product_price' => [
            'title' => 'Produkte ohne Preis',
            'found' => 'Veröffentlichte Produkte haben keinen Preis oder einen Preis von null.',
            'why' => 'Ein Besucher erfährt nicht, was es kostet, und das Produkt kann nicht in Shopping-Ergebnissen erscheinen.',
            'fix' => 'Legen Sie den Preis fest, oder nehmen Sie die Produkte vom Netz, die noch nicht verkauft werden.',
        ],
    ],
];
