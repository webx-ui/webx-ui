<?php

declare(strict_types=1);

return [
    'review-human' => 'Etapa :position/:count «:stage» aguarda sua verificação (tentativa :attempt)',
    'review-auto' => 'Etapa :position/:count «:stage» aguarda a verificação de um agente (tentativa :attempt)',
    'blocked' => 'O agente da etapa :position/:count «:stage» pergunta:',
    'rework' => 'Etapa :position/:count «:stage» foi devolvida (tentativa :attempt)',
    'stale' => 'Etapa :position/:count «:stage» está em andamento há tempo demais — :agent, horas: :hours',
    'done' => 'A tarefa foi concluída. Etapas aceitas: :count',
    'result' => 'Resultado: :result',
    'test' => 'Mensagem de teste de :site: as notificações da fila de tarefas chegarão aqui.',
];
