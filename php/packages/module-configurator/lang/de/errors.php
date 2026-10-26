<?php

declare(strict_types=1);

return [
    'schema' => [
        'invalid' => 'Das Schema ist kein JSON-Objekt.',
        'version' => 'Unbekannte Schemaversion: Dieses Modul liest Version 1.',
    ],
    'currency' => [
        'invalid' => 'Die Währung ist ein dreistelliger ISO-4217-Code in Großbuchstaben, etwa UAH oder EUR.',
    ],
    'pricing' => [
        'round' => 'Der Rundungsschritt ist eine Zahl über 0.',
    ],
    'discount' => [
        'percent' => 'Der Rabatt ist ein Prozentsatz über 0 und unter 100.',
        'until' => 'Der letzte Rabatttag ist ein Datum JJJJ-MM-TT.',
    ],
    'steps' => [
        'empty' => 'Ein Konfigurator braucht mindestens einen Schritt.',
    ],
    'key' => [
        'invalid' => 'Der Schlüssel „:key“ besteht nicht aus a-z, 0-9 und _ mit einem Buchstaben am Anfang.',
        'duplicate' => 'Der Schlüssel „:key“ ist vergeben: Schritte und Maßfelder teilen sich die Schlüssel.',
    ],
    'type' => [
        'unknown' => 'Unbekannter Schritttyp: cards, swatches, buttons oder dimensions.',
    ],
    'options' => [
        'empty' => 'Ein Auswahlschritt braucht mindestens eine Option.',
    ],
    'fields' => [
        'empty' => 'Ein Maßschritt braucht mindestens ein Feld.',
    ],
    'value' => [
        'invalid' => 'Der Wert „:value“ ist kein Slug: a-z, 0-9, - und _.',
        'duplicate' => 'Der Wert „:value“ kommt in diesem Schritt zweimal vor.',
    ],
    'label' => [
        'missing' => 'Ein Name in der Standardsprache (:locale) ist nötig.',
    ],
    'text' => [
        'invalid' => 'Ein Text ist eine Zeichenkette oder eine Reihe von Sprachen.',
    ],
    'image' => [
        'missing' => 'Wählen Sie ein Bild aus der Mediathek.',
        'unknown' => 'Die Mediathek hat keine Datei „:key“.',
    ],
    'swatch' => [
        'invalid' => 'Ein Muster ist #rgb, #rrggbb oder ein Verlauf aus Hex-Farben.',
    ],
    'price' => [
        'invalid' => 'Ein Preis ist eine Zahl.',
        'per_m2' => 'Ein Preis pro m² braucht eine Fläche: Geben Sie den Optionen eines Schritts eine Flächenformel.',
    ],
    'area' => [
        'main_count' => 'Nur die Optionen eines Schritts dürfen eine Flächenformel haben.',
        'missing' => 'Jede Option des Hauptschritts braucht eine Flächenformel.',
        'syntax' => 'Die Formel ist bei Zeichen :position falsch: :reason.',
        'unknown_key' => 'Die Formel verwendet „:key“, das kein Maßfeld ist.',
    ],
    'range' => [
        'invalid' => 'Der Bereich ist falsch: min über max oder ein Schritt nicht über 0.',
    ],
    'limits' => [
        'unknown_field' => 'limits nennt „:key“, das kein Maßfeld ist.',
    ],
    'defaults' => [
        'unknown_field' => 'defaults nennt „:key“, das kein Maßfeld ist.',
    ],
    'condition' => [
        'invalid' => 'Eine Bedingung ist ein Schritt und die Werte, mit denen er gewählt ist.',
        'unknown_step' => 'Es gibt keinen Schritt „:step“.',
        'forward' => 'Der Schritt „:step“ steht nicht über diesem: Eine Bedingung schaut nur nach oben.',
        'not_choice' => 'Der Schritt „:step“ ist ein Maßschritt: Eine Bedingung nennt einen Schritt mit Optionen.',
        'unknown_value' => 'Der Schritt „:step“ hat keine Option „:value“.',
    ],
    'initial' => [
        'unknown' => 'Die Startauswahl „:key“ ist weder Schritt noch Feld noch einer seiner Werte.',
    ],
    'previews' => [
        'condition' => 'Eine Bildregel nennt einen Schritt oder Wert, den es nicht gibt.',
    ],
    'values' => [
        'range' => 'Das Maß „:key“ liegt zwischen :min und :max.',
        'number' => 'Das Maß „:key“ ist eine Zahl.',
    ],
];
