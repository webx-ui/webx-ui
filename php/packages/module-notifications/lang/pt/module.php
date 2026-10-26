<?php

declare(strict_types=1);

return [
    'title' => 'Notificações',
    'channel' => 'Canal',
    'copy-name' => ':name (cópia)',
    'unsaved' => 'Canal não guardado',
    'permissions' => [
        'view' => 'Ver os canais e o registo de envios, incluindo o que os visitantes enviaram através de formulários.',
        'manage' => 'Configurar canais e os seus segredos, enviar testes, procurar chats e reenviar notificações falhadas.',
    ],
    'statuses' => [
        'queued' => 'Em fila',
        'sent' => 'Enviado',
        'trapped' => 'Transporte de teste',
        'failed' => 'Falhou',
        'skipped' => 'Ignorado',
    ],
    'reasons' => [
        'channel_deleted' => 'O canal foi eliminado',
        'channel_disabled' => 'O canal está desativado',
        'wrong_event' => 'O canal é para outro evento',
        'provider_missing' => 'O fornecedor não está instalado',
        'no_recipients' => 'O canal não tem destinatários',
        'no_address' => 'O evento não trouxe nenhum endereço',
        'admin_deleted' => 'O administrador foi eliminado',
        'admin_inactive' => 'O administrador está desativado',
    ],
];
