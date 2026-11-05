<?php

declare(strict_types=1);

return [
    'currency' => 'Ein Feed verwendet die Währung der Website: :currency.',
    'currency-unset' => 'Die Website hat keine Währung: Setzen Sie WEBX_CATALOG_CURRENCY.',
    'filter-facet' => 'Der Filter des Feeds nennt [:facet], das es auf der Website nicht gibt.',
    'source-property' => 'Der Feed nimmt die MPN aus der Eigenschaft [:property], die es auf der Website nicht gibt.',
    'no-item' => 'Der Feed hat keine Produktvorlage.',
    'template' => 'Die Vorlage ist nicht lesbar: :reason',
    'too-large' => 'Die Datei überschreitet die zulässige Größe eines Feeds.',
    'stale' => 'Der Aufbau kam nicht mehr voran und wurde geschlossen.',
    'file-xml' => 'Die Datei ist kein gültiges XML, Zeile :line: :reason',
    'file-json' => 'Kopf und Fuß ergeben kein gültiges JSON: :reason',
    'file-json-item' => 'Produkt :product ergibt kein gültiges JSON: :reason',
    'file-csv' => 'Zeile :line der Datei hat :found Felder statt :expected.',
    'sample-json' => 'Die Probe ist kein gültiges JSON, Zeile :line: :reason',
    'sample-place' => 'Teil „:part“, Zeile :line: :fragment',
    'unknown-variable' => '{{:name}} ist im Teil „:part“ nicht bekannt.',
    'no-products' => 'Noch passt kein Produkt zum Feed: Nur Kopf und Fuß wurden geprüft.',
];
