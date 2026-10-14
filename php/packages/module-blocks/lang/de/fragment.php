<?php

declare(strict_types=1);

return [
    'choose' => 'Gemeinsamen Block wählen',
    'none' => 'Die Website hat noch keine gemeinsamen Blöcke. Ein Entwickler deklariert einen unter regions in config/webx-blocks.php, mit fragment auf true.',
    'missing' => '„:name“ ist kein gemeinsamer Block der Website mehr: Hier wird nichts angezeigt.',
    'status-live' => 'Auf der Website',
    'status-edits' => 'Auf der Website, Änderungen ausstehend',
    'status-fallback' => 'Nicht veröffentlicht: Die Website zeigt die Ersatzansicht',
    'status-empty' => 'Leer',
    'explain' => 'Dieser Block zeigt einen gemeinsamen Block der Website. Eine Änderung daran wirkt auf jeder Seite, auf der er steht.',
    'edit' => 'Für alle Seiten bearbeiten',
    'detach' => 'Für diese Seite eigenständig machen',
    'detach-title' => '„:title“ für diese Seite eigenständig machen?',
    'detach-text' => 'Seine Blöcke werden an seiner Stelle in diese Seite kopiert, so wie sie auf der Website sind. Sie folgen dem gemeinsamen Block nicht mehr: Seine Änderungen erreichen diese Seite nicht mehr.',
    'detached' => 'Die Blöcke von „:title“ gehören jetzt dieser Seite.',
    'unpublished' => 'Der gemeinsame Block ist noch nicht veröffentlicht: Es gibt nichts zu kopieren.',
    'group-layout' => 'Kopf- und Fußbereich',
    'group-shared' => 'Gemeinsame Blöcke',
    'group-shared-help' => 'Hier einmal bearbeitet und überall gezeigt, wo eine Seite oder ein Template der Website sie platziert.',
    'count' => 'Seiten: :count',
    'used-on' => 'Wo er steht',
    'used-nowhere' => 'Noch keine Seite platziert ihn.',
    'templates' => 'Templates der Website, die ihn per Tag ausgeben, sind nicht aufgeführt.',
    'draft-only' => 'Im Entwurf',
    'show-here' => 'Auf dieser Seite in der Vorschau zeigen',
    'publish-text' => 'Jede Seite, die ihn platziert, zeigt diese Blöcke statt der Ansicht aus dem Code.',
];
