<?php

declare(strict_types=1);

/*
 * The words of the categories every module shares (`WebxUi\Admin\Categories`). A module that
 * calls its categories something else — rubrics — says its own refusal instead.
 */

return [
    'in-use' => 'Einträge darin: :count. Verschieben Sie sie zuerst in eine andere Kategorie.',
    'slug-shape' => 'Buchstaben, Ziffern und einzelne Bindestriche dazwischen.',

    // The shared screens of categories (`categories/` in @webx-ui/module-admin). A module
    // that calls its categories something else hands its own keys in over these.
    'new' => 'Neue Kategorie',
    'empty' => 'Noch keine Kategorien.',
    'empty-help' => 'Eine Kategorie fasst Einträge zusammen. Ein Eintrag kann in mehreren sein.',
    'order' => 'Die Reihenfolge hier ist die Reihenfolge auf der Website',
    'hidden' => 'Auf der Website ausgeblendet',
    'no-address' => 'Keine Adresse in dieser Sprache',
    'count' => 'Einträge: :count',
    'show-items' => 'Ihre Einträge zeigen',
    'edit' => 'Bearbeiten',
    'open-on-site' => 'Auf der Website öffnen',
    'delete' => 'Löschen',
    'delete-blocked' => 'Solange sie Einträge enthält, kann sie nicht gelöscht werden — verschieben Sie diese zuerst.',
    'delete-title' => '„:name“ löschen?',
    'delete-text' => 'Sie kommt in den Papierkorb und verschwindet von der Website, ihre Adresse wird frei.',
    'deleted' => 'Die Kategorie ist im Papierkorb.',
    'cancel' => 'Abbrechen',
    'create' => 'Erstellen',
    'save' => 'Speichern',
    'saved' => 'Gespeichert.',
    'save-failed' => 'Nicht gespeichert — sehen Sie sich die markierten Felder an.',
    'reorder-failed' => 'Die neue Reihenfolge wurde nicht gespeichert.',
    'field-title' => 'Name',
    'field-slug' => 'Adresse',
    'address-moving' => 'Die Adresse ändert sich. Die alte funktioniert weiter und führt zur neuen.',
    'untitled' => 'Ohne Titel',
    'trail' => 'Wo Sie sind',
    'leave-title' => 'Ohne Speichern verlassen?',
    'leave-text' => 'Was hier seit dem letzten Speichern geändert wurde, geht verloren.',
    'leave' => 'Verlassen',
    'field-main' => 'Haupt',
    'field-add' => 'Kategorie hinzufügen',
    'field-remove' => 'Aus dieser Kategorie nehmen',
    'field-empty' => 'Noch in keiner Kategorie.',
    'field-none-left' => 'Alle Kategorien sind bereits gewählt.',
    'order-all' => 'Ziehen, um die Reihenfolge auf der Website zu ändern.',
    'order-category' => 'Ziehen, um die Reihenfolge in dieser Kategorie zu ändern. Der Rest der Liste behält seine eigene.',
    'order-locked' => 'Suche und Filter leeren, um die Reihenfolge zu ändern — ziehen lässt sich nur die ganze Liste oder eine Kategorie.',
    'unknown' => 'Eine der gewählten Kategorien gibt es nicht mehr.',
];
