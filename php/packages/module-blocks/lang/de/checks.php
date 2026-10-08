<?php

declare(strict_types=1);

return [
    'no-marker' => 'Kein data-wx-block an der Wurzel: Das Skript läuft nicht, und das Panel kann den Block in der Vorschau nicht hervorheben.',
    'stray-selectors' => 'Selektoren außerhalb des Blockpräfixes .b-:slug: :selectors',
    'bare-selectors' => 'Elementselektoren greifen auf die ganze Site: :selectors',
    'media-query' => '@media misst das Fenster. Ein Block richtet sich nach seinem Container: Verwenden Sie @container.',
    'variables-missing' => 'Das Template verwendet :variables, die das Schema nicht deklariert. Die Veröffentlichung wird abgelehnt.',
    'ok-marker' => 'Die Wurzel trägt data-wx-block.',
    'ok-prefix' => 'Jeder Selektor beginnt mit .b-:slug.',
    'ok-bare' => 'Keine nackten Elementselektoren.',
    'ok-container' => 'Die Breite wird per Container-Query bestimmt.',
    'ok-variables' => 'Jede Variable des Templates ist ein Feld des Schemas.',
    'blocks' => [
        'stray_values' => [
            'title' => 'Blockwerte für Felder, die der Typ nicht hat',
            'found' => 'Blöcke enthalten Werte für Felder, die ihr Typ nicht definiert — übrig von einem Import oder einem aus dem Typ entfernten Feld.',
            'why' => 'Besucher sehen davon nichts, aber es steckt in den Daten des Editors und in dem, was ein Agent liest, und taucht als falsche Bezeichnung eines Blocks auf.',
            'fix' => 'Entfernen Sie sie mit der Korrektur oder für die ganze Website mit php artisan webx:blocks:prune. Ein Block eines Typs, den es nicht mehr gibt, bleibt unberührt. Elemente eines Repeaters werden mit dessen Feldern verglichen.',
        ],
        'unknown_shortcodes' => [
            'title' => 'Vertippte Shortcodes',
            'found' => 'Ein Text enthält eine Klammer, die fast ein Shortcode dieser Website ist oder Argumente hat, aber keiner ist.',
            'why' => 'Ersetzt werden nur registrierte Shortcodes. Alles andere wird genau so gedruckt, wie es getippt wurde, mit Klammern — für jeden Besucher sichtbar.',
            'fix' => 'Korrigieren Sie den Namen auf den vorgeschlagenen oder schreiben Sie [[name]], wenn die Seite die Klammern zeigen soll. Die Liste steht in der Shortcode-Hilfe des Felds und unter «Einstellungen» → «Shortcodes».',
        ],
        'hardcoded_values' => [
            'title' => 'Werte statt Shortcode',
            'found' => 'Ein Text enthält eine Telefonnummer, eine E-Mail-Adresse oder einen anderen Wert, den ein Shortcode aus «Einstellungen» → «Shortcodes» bereits hält.',
            'why' => 'Heute stimmt er, am Tag der Änderung nicht mehr: Der Shortcode ändert sich überall, ein von Hand getippter Wert nur dort, wo jemand daran denkt.',
            'fix' => 'Ersetzen Sie den Wert durch den vorgeschlagenen Shortcode, z. B. [phone]. Den Link macht der Shortcode selbst.',
        ],
    ],
    'syntax' => 'Die Vorlage lässt sich nicht kompilieren: :reason. Die Veröffentlichung wird abgelehnt.',
    'unknown-field-type' => 'Felder eines Typs, den die Website nicht kennt: :fields. Das Formular zeigt an ihrer Stelle eine Warnung, und ihre Werte prüft niemand.',
    'field-id' => 'Feld-IDs bestehen aus Buchstaben, Ziffern, _ und -, am Anfang ein Buchstabe: :ids.',
    'marker-slug' => 'Die Wurzel ist mit data-wx-block=":marker" markiert, die Kennung lautet aber «:slug»: Skript und Panel finden den Block nur über die exakte Kennung. Die Veröffentlichung wird abgelehnt.',
];
