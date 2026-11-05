<?php

declare(strict_types=1);

return [
    'template-unclosed' => 'Der Abschnitt {{#:section}} ist nicht geschlossen: Beenden Sie ihn mit {{/:section}}.',
    'template-unexpected' => '{{/:section}} schließt einen Abschnitt, der nie geöffnet wurde.',
    'template-tag' => 'Das Tag {{:tag ist nicht geschlossen: Beenden Sie es mit }}.',
    'template-name' => '{{:name}} ist kein Variablenname: Kleinbuchstaben, Ziffern und _, höchstens ein Punkt.',
    'template-partial' => 'Partials ({{>name}}) sind in einer Nachrichtenvorlage nicht verfügbar.',
    'template-delimiters' => 'Das Ändern der Begrenzer ({{=<% %>=}}) ist in einer Nachrichtenvorlage nicht verfügbar.',
    'template-pragma' => 'Pragmas und Blöcke ({{%…}}, {{$…}}) sind in einer Nachrichtenvorlage nicht verfügbar.',
    'template-cdata' => 'Eine Variable darf nicht in einem literalen CDATA-Block stehen: {{:name}} würde doppelt maskiert. Entfernen Sie das CDATA darum und nehmen Sie die _cdata-Variable, etwa {{description_cdata}}.',
];
