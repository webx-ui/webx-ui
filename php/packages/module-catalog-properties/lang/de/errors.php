<?php

declare(strict_types=1);

return [
    'type' => 'Eine Eigenschaft ist eines von: :types.',
    'type-fixed' => 'Der Typ einer Eigenschaft ändert sich nach dem Speichern nicht mehr: Legen Sie eine neue Eigenschaft an und verschieben Sie die Werte.',
    'code' => 'Lateinische Buchstaben, Ziffern und einzelne Bindestriche, höchstens :max Zeichen.',
    'code-taken' => 'Dieser Code ist in dieser Sprache bereits vergeben an: :holder.',
    'slug' => 'Lateinische Buchstaben, Ziffern und einzelne Bindestriche.',
    'slug-taken' => 'Ein anderer Wert dieser Eigenschaft hat in dieser Sprache diesen Slug.',
    'color' => 'Eine Farbe ist #rrggbb.',
    'group' => 'Eine solche Gruppe gibt es nicht.',
    'group-in-use' => 'Die Gruppe enthält Eigenschaften. Eigenschaften: :count',
    'unknown-property' => 'Eine solche Eigenschaft gibt es nicht.',
    'inherited' => '«:property» ist bereits in der Auswahl von «:category» darüber; eine Kategorie erbt sie.',
    'outside-set' => 'Diese Eigenschaft ist nicht in der Auswahl der Hauptkategorie des Produkts.',
    'number' => 'Erwartet wird eine Zahl.',
    'text' => 'Erwartet wird ein Text oder eine Zuordnung von Sprachen.',
    'one-value' => 'Diese Eigenschaft nimmt einen Wert.',
    'unknown-value' => 'Einen solchen Wert dieser Eigenschaft gibt es nicht.',
    'unknown-slug' => '«:property» hat keinen Wert «:slug». Legen Sie ihn zuerst mit catalog_property_values_create an, oder geben Sie die ID an.',
    'leaves-only' => 'Hier lässt sich nur ein Wert wählen, unter dem nichts liegt.',
    'value-in-use' => 'Produkte haben diesen Wert — führen Sie ihn stattdessen mit einem anderen zusammen. Produkte: :count',
    'values-select' => 'Nur ein Verzeichnis hat Werte.',
    'not-tree' => 'Die Werte dieser Eigenschaft sind kein Baum.',
    'move-into-itself' => 'Ein Wert kann nicht unter sich selbst liegen.',
    'merge-other' => 'Ein Wert wird mit einem anderen Wert derselben Eigenschaft zusammengeführt.',
    'merge-descendant' => 'Ein Wert kann nicht mit einem Wert unter ihm zusammengeführt werden.',
    'intervals-number' => 'Nur eine Zahl hat Intervalle.',
    'interval-ends' => 'Das obere Ende muss über dem unteren liegen.',
];
