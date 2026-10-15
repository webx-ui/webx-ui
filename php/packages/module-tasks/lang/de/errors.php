<?php

declare(strict_types=1);

return [
    'agent-name' => 'Benennen Sie den Agenten: Der Sitzungsname wird in der Stufe und im Journal festgehalten.',
    'project-archived' => 'Das Projekt :project ist archiviert: keine neuen Aufgaben, und nichts davon wird ausgegeben.',
    'task-closed' => 'Die Aufgabe ist :status und nimmt keine Änderungen mehr an.',
    'task-started' => 'Die Arbeit an der Aufgabe hat begonnen; nur die Priorität und noch nicht übernommene Stufen können sich ändern. Nicht: :fields.',
    'task-not-draft' => 'Nur ein Entwurf kann bereitgestellt werden; diese Aufgabe ist :status.',
    'task-no-stages' => 'Eine Aufgabe ohne Stufen hat nichts auszugeben. Fügen Sie mindestens eine Stufe hinzu.',
    'task-deleted' => 'Die Aufgabe ist gelöscht.',
    'person-only' => 'Nur ein Mensch kann die Frage eines Agenten beantworten.',
    'stage-elsewhere' => 'Eine hier genannte Stufe gehört zu einer anderen Aufgabe oder ist doppelt genannt.',
    'last-stage' => 'Dies ist die letzte verbleibende Stufe. Brechen Sie stattdessen die Aufgabe ab.',
    'stage-transition' => 'Eine Stufe kann nicht von :from zu :to wechseln.',
    'stage-not' => 'Die Stufe muss dafür :status sein; sie ist :actual.',
    'not-holder' => 'Die Stufe gehört :assignee: Nur dieser Agent arbeitet daran.',
    'human-review' => 'Diese Stufe prüft ein Mensch: Ein Agent kann sie weder annehmen noch zurückgeben.',
    'own-work' => 'Ein Agent prüft nicht seine eigene Arbeit.',
    'text-required' => ':what darf nicht leer sein.',
    'stage-missing' => 'Nach Arbeitsbeginn bleiben alle Stufen in der Liste; eine nicht benötigte Stufe wird abgebrochen. Es fehlen: :stages.',
    'stage-new-at-end' => 'Neue Stufen kommen ans Ende.',
    'stage-started-order' => 'Eine begonnene oder abgebrochene Stufe bleibt an ihrem Platz.',
    'stage-started' => 'Stufe :stage ist :status und kann nicht mehr geändert werden.',
    'notify-not-configured' => 'Benachrichtigungen sind nicht eingerichtet: Fügen Sie den Bot-Token und mindestens einen Chat hinzu und speichern Sie.',
];
