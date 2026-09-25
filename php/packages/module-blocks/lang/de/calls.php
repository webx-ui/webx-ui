<?php

declare(strict_types=1);

return [
    'kind' => 'Ein Typ ist entweder ein Block oder eine Komponente.',
    'kind-in-use' => 'Der Block steht auf Seiten und kann keine Komponente werden. Seiten: :count.',
    'unknown-call' => '„:type“ ist kein Blocktyp: Der Aufruf gibt auf der Website nichts aus.',
    'dynamic-call' => 'Der aufgerufene Typ ist nicht ausgeschrieben: Seine Veröffentlichung prüft diese Vorlage nicht.',
    'delete-used-by' => 'Andere Typen rufen diesen auf, und ihre Vorlagen hätten eine Lücke. Entfernen Sie zuerst die Aufrufe.',
    'delete-used-by-one' => 'Aufgerufen von „:title“ (:slug)',
    'publish-cycle' => 'Die Typen rufen sich im Kreis auf: :path.',
    'publish-breaks-parent' => 'Macht „:parent“ auf seinem Beispiel kaputt: :reason',
    'publish-breaks-parent-on' => 'Macht „:parent“ auf „:entity“ kaputt: :reason',
    'publish-breaks-declared' => 'Macht die Stelle kaputt, von der das Modul :module ihn aufruft: :reason',
    'customise-exists' => 'Ein Typ mit dieser Kennung existiert bereits.',
    'customised-from' => 'Aus :view',
];
