<?php

declare(strict_types=1);

return [
    'review-human' => 'Etapa :position/:count «:stage» espera tu revisión (intento :attempt)',
    'review-auto' => 'Etapa :position/:count «:stage» espera la revisión de un agente (intento :attempt)',
    'blocked' => 'El agente de la etapa :position/:count «:stage» pregunta:',
    'rework' => 'Etapa :position/:count «:stage» devuelta (intento :attempt)',
    'stale' => 'Etapa :position/:count «:stage» lleva demasiado tiempo en curso — :agent, horas: :hours',
    'done' => 'La tarea está hecha. Etapas aceptadas: :count',
    'result' => 'Resultado: :result',
    'test' => 'Mensaje de prueba de :site: aquí llegarán las notificaciones de la cola de tareas.',
];
