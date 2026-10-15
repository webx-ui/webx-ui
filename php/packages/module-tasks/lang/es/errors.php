<?php

declare(strict_types=1);

return [
    'agent-name' => 'Nombra al agente: el nombre de la sesión es lo que registran la etapa y el diario.',
    'project-archived' => 'El proyecto :project está archivado: sin tareas nuevas y no se reparte nada de él.',
    'task-closed' => 'La tarea está :status y no admite más cambios.',
    'task-started' => 'El trabajo en la tarea ha empezado; solo pueden cambiar su prioridad y las etapas que nadie ha tomado. No: :fields.',
    'task-not-draft' => 'Solo un borrador puede ponerse en marcha; esta tarea está :status.',
    'task-no-stages' => 'Una tarea sin etapas no tiene nada que repartir. Añade al menos una etapa.',
    'task-deleted' => 'La tarea está eliminada.',
    'person-only' => 'Solo una persona puede responder la pregunta de un agente.',
    'stage-elsewhere' => 'Una etapa nombrada aquí pertenece a otra tarea o está nombrada dos veces.',
    'last-stage' => 'Es la última etapa que queda. Cancela la tarea en su lugar.',
    'stage-transition' => 'Una etapa no puede pasar de :from a :to.',
    'stage-not' => 'Para esto la etapa tiene que estar :status; está :actual.',
    'not-holder' => 'La etapa la tiene :assignee: solo ese agente trabaja en ella.',
    'human-review' => 'Esta etapa la revisa una persona: un agente no puede aceptarla ni devolverla.',
    'own-work' => 'Un agente no revisa su propio trabajo.',
    'text-required' => 'El campo :what no puede estar vacío.',
    'stage-missing' => 'Una vez empezado el trabajo, todas las etapas siguen en la lista; una etapa que no hace falta se cancela. Faltan: :stages.',
    'stage-new-at-end' => 'Las etapas nuevas van al final.',
    'stage-started-order' => 'Una etapa empezada o cancelada se queda donde está.',
    'stage-started' => 'La etapa :stage está :status y ya no se puede cambiar.',
    'notify-not-configured' => 'Las notificaciones no están configuradas: añade el token del bot y al menos un chat, y guarda.',
];
