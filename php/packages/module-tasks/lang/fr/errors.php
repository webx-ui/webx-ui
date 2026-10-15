<?php

declare(strict_types=1);

return [
    'agent-name' => 'Nommez l’agent : le nom de session est ce que l’étape et le journal enregistrent.',
    'project-archived' => 'Le projet :project est archivé : aucune nouvelle tâche, et rien n’en est distribué.',
    'task-closed' => 'La tâche est :status et n’accepte plus de modifications.',
    'task-started' => 'Le travail sur la tâche a commencé ; seuls sa priorité et les étapes que personne n’a prises peuvent changer. Pas : :fields.',
    'task-not-draft' => 'Seul un brouillon peut être mis en travail ; cette tâche est :status.',
    'task-no-stages' => 'Une tâche sans étapes n’a rien à distribuer. Ajoutez au moins une étape.',
    'task-deleted' => 'La tâche est supprimée.',
    'person-only' => 'Seule une personne peut répondre à la question d’un agent.',
    'stage-elsewhere' => 'Une étape nommée ici appartient à une autre tâche, ou est nommée deux fois.',
    'last-stage' => 'C’est la dernière étape restante. Annulez plutôt la tâche.',
    'stage-transition' => 'Une étape ne peut pas passer de :from à :to.',
    'stage-not' => 'L’étape doit être :status pour cela ; elle est :actual.',
    'not-holder' => 'L’étape est tenue par :assignee : seul cet agent y travaille.',
    'human-review' => 'Cette étape est vérifiée par une personne : un agent ne peut ni l’accepter ni la renvoyer.',
    'own-work' => 'Un agent ne vérifie pas son propre travail.',
    'text-required' => 'Le champ :what ne peut pas être vide.',
    'stage-missing' => 'Une fois le travail commencé, toutes les étapes restent dans la liste ; une étape inutile est annulée. Manquent : :stages.',
    'stage-new-at-end' => 'Les nouvelles étapes vont à la fin.',
    'stage-started-order' => 'Une étape commencée ou annulée reste à sa place.',
    'stage-started' => 'L’étape :stage est :status et ne peut plus être modifiée.',
    'notify-not-configured' => 'Les notifications ne sont pas configurées : ajoutez le jeton du bot et au moins un chat, puis enregistrez.',
];
