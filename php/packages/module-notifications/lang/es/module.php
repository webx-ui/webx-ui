<?php

declare(strict_types=1);

return [
    'title' => 'Notificaciones',
    'channel' => 'Canal',
    'copy-name' => ':name (copia)',
    'unsaved' => 'Canal sin guardar',
    'permissions' => [
        'view' => 'Ver los canales y el registro de envíos, incluido lo que los visitantes enviaron mediante formularios.',
        'manage' => 'Configurar canales y sus secretos, enviar pruebas, buscar chats y reenviar notificaciones fallidas.',
    ],
    'statuses' => [
        'queued' => 'En cola',
        'sent' => 'Enviado',
        'failed' => 'Fallido',
        'skipped' => 'Omitido',
    ],
    'reasons' => [
        'channel_deleted' => 'El canal está eliminado',
        'channel_disabled' => 'El canal está desactivado',
        'wrong_event' => 'El canal es para otro evento',
        'provider_missing' => 'El proveedor no está instalado',
        'no_recipients' => 'El canal no tiene destinatarios',
        'no_address' => 'El evento no trajo ninguna dirección',
        'admin_deleted' => 'El administrador está eliminado',
        'admin_inactive' => 'El administrador está desactivado',
    ],
];
