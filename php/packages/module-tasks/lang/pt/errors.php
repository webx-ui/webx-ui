<?php

declare(strict_types=1);

return [
    'agent-name' => 'Dê um nome ao agente: o nome da sessão é o que a etapa e o diário registam.',
    'project-archived' => 'O projeto :project está arquivado: sem tarefas novas e nada dele é distribuído.',
    'task-closed' => 'A tarefa está :status e não aceita mais alterações.',
    'task-started' => 'O trabalho na tarefa começou; só podem mudar a prioridade e as etapas que ninguém pegou. Não: :fields.',
    'task-not-draft' => 'Só um rascunho pode ser posto em andamento; esta tarefa está :status.',
    'task-no-stages' => 'Uma tarefa sem etapas não tem nada para distribuir. Adicione pelo menos uma etapa.',
    'task-deleted' => 'A tarefa foi eliminada.',
    'person-only' => 'Só uma pessoa pode responder à pergunta de um agente.',
    'stage-elsewhere' => 'Uma etapa indicada aqui pertence a outra tarefa ou está indicada duas vezes.',
    'last-stage' => 'Esta é a última etapa que resta. Cancele antes a tarefa.',
    'stage-transition' => 'Uma etapa não pode passar de :from para :to.',
    'stage-not' => 'Para isto a etapa tem de estar :status; está :actual.',
    'not-holder' => 'A etapa está com :assignee: só esse agente trabalha nela.',
    'human-review' => 'Esta etapa é verificada por uma pessoa: um agente não a pode aceitar nem devolver.',
    'own-work' => 'Um agente não verifica o próprio trabalho.',
    'text-required' => 'O campo :what não pode estar vazio.',
    'stage-missing' => 'Depois de o trabalho começar, todas as etapas ficam na lista; uma etapa desnecessária é cancelada. Faltam: :stages.',
    'stage-new-at-end' => 'As etapas novas vão para o fim.',
    'stage-started-order' => 'Uma etapa começada ou cancelada fica no seu lugar.',
    'stage-started' => 'A etapa :stage está :status e já não pode ser alterada.',
    'notify-not-configured' => 'As notificações não estão configuradas: adicione o token do bot e pelo menos um chat, e salve.',
];
