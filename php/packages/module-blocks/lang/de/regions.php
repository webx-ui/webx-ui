<?php

declare(strict_types=1);

// The regions of the layout (the regions spec): the words a declaration may point at with
// `trans::webx-blocks::regions.header`, and what the server says about a region.
return [
    'header' => 'Kopfzeile',
    'footer' => 'Fußzeile',
    'usage' => 'Bereich „:title“',
    'preview-failed' => 'Ein Block dieses Bereichs schlägt fehl. Auf der Website erscheint stattdessen das Markup aus dem Code.',
    'not-in-registry' => ':path ist keine Seite im Adressregister der Website, daher wird der Bereich auf einer leeren Seite des Layouts gezeigt.',
    'too-many' => 'Der Bereich fasst höchstens :max Blöcke.',
    'not-allowed' => 'Der Block „:type“ kann nicht in diesen Bereich gesetzt werden.',
    'refused' => 'Der Bereich nimmt diese Blöcke nicht an.',
    'conflict' => 'Der Bereich wurde geändert, seit Sie ihn geöffnet haben. Laden Sie ihn neu.',
    'failed-block' => 'Block „:type“ (:key) schlägt fehl: :reason',
    'not-published' => 'Nicht veröffentlicht: ein Block des Entwurfs lässt sich nicht darstellen.',
    'nothing-to-publish' => 'Der Bereich wurde nie gespeichert: es gibt nichts zu veröffentlichen.',
    'no-version' => 'Der Bereich hat keine Version :number.',
    'no-fallback' => 'Das Layout hat für diesen Bereich noch keine View genannt, oder die View fehlt.',
    'adopt-taken' => 'Ein Blocktyp „:slug“ existiert bereits.',
    'adopt-failed' => 'Das Markup konnte nicht zu einem Blocktyp werden.',
    'adopt-forbidden' => 'Um das Markup in einen Blocktyp zu verschieben, braucht es das Recht, Blocktypen zu bearbeiten.',
];
