<?php

declare(strict_types=1);

// The journal of the exchange with gateways (spec §20): directions, kinds, signatures, outcomes.
return [
    'title' => 'Troca com o gateway',
    'directions' => [
        'out' => 'Para o gateway',
        'in' => 'Do gateway',
        'browser' => 'Pelo navegador do comprador',
        'internal' => 'Decisão do site',
    ],
    'kinds' => [
        'start' => 'Pagamento aberto',
        'status' => 'Consulta de estado',
        'cancel' => 'Cancelamento',
        'complete' => 'Aprovação concluída',
        'capture' => 'Pedido de captura',
        'void' => 'Pedido de liberação',
        'connection' => 'Teste de ligação',
        'key' => 'Chave de assinatura',
        'other' => 'Outro pedido',
        'notify' => 'Notificação',
        'handoff' => 'Enviado ao gateway',
        'return' => 'Regresso do gateway',
        'expire' => 'Expirado',
        'manual' => 'Marcado à mão',
        'captured' => 'Capturado',
        'released' => 'Retenção liberada',
    ],
    'signature' => [
        'valid' => 'Assinatura válida',
        'invalid' => 'Assinatura inválida',
        'untrusted' => 'Sem assinatura — estado pedido ao gateway',
        'none' => 'Não verificada',
    ],
    'outcomes' => [
        'applied' => 'Aplicado',
        'same' => 'Nada de novo',
        'ignored' => 'Ignorado',
        'duplicate' => 'Repetição',
        'rejected' => 'Rejeitado',
        'unmatched' => 'Pagamento inexistente',
        'error' => 'Erro',
    ],
    'truncated' => 'Cortado no limite',
    'download' => 'Transferir',
    'size' => 'Tamanho',
    'masked' => 'Segredos ocultos',
];
