<?php

declare(strict_types=1);

return [
    'agent-name' => 'Nazwij agenta: nazwa sesji jest zapisywana w etapie i w dzienniku.',
    'project-archived' => 'Projekt :project jest zarchiwizowany: bez nowych zadań i nic z niego nie jest wydawane.',
    'task-closed' => 'Zadanie ma status :status i nie przyjmuje już zmian.',
    'task-started' => 'Praca nad zadaniem się zaczęła; zmienić można tylko priorytet i etapy, których nikt jeszcze nie wziął. Nie: :fields.',
    'task-not-draft' => 'Do pracy można przekazać tylko szkic; to zadanie ma status :status.',
    'task-no-stages' => 'Zadanie bez etapów nie ma czego wydać. Dodaj co najmniej jeden etap.',
    'task-deleted' => 'Zadanie zostało usunięte.',
    'person-only' => 'Na pytanie agenta odpowiada tylko człowiek.',
    'stage-elsewhere' => 'Wskazany etap należy do innego zadania albo jest wskazany dwa razy.',
    'last-stage' => 'To ostatni pozostały etap. Anuluj całe zadanie.',
    'stage-transition' => 'Etap nie może przejść z :from do :to.',
    'stage-not' => 'Do tego etap musi mieć status :status; ma :actual.',
    'not-holder' => 'Etap wziął :assignee: pracuje nad nim tylko ten agent.',
    'human-review' => 'Ten etap sprawdza człowiek: agent nie może go ani przyjąć, ani zwrócić.',
    'own-work' => 'Agent nie sprawdza własnej pracy.',
    'text-required' => 'Pole :what nie może być puste.',
    'stage-missing' => 'Po rozpoczęciu pracy wszystkie etapy zostają na liście; niepotrzebny etap się anuluje. Brakuje: :stages.',
    'stage-new-at-end' => 'Nowe etapy dodaje się na końcu.',
    'stage-started-order' => 'Rozpoczęty lub anulowany etap zostaje na swoim miejscu.',
    'stage-started' => 'Etap :stage ma status :status i nie można go już zmienić.',
    'notify-not-configured' => 'Powiadomienia nie są skonfigurowane: dodaj token bota i co najmniej jeden czat, a następnie zapisz.',
];
