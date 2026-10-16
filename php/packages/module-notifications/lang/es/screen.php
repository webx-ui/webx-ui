<?php

declare(strict_types=1);

return [
    'name' => 'Nombre',
    'name-help' => 'Solo para administradores.',
    'provider' => 'Proveedor',
    'provider-help' => 'Se elige una vez: un canal de otro tipo es un canal nuevo.',
    'event' => 'Para el evento',
    'event-help' => 'Con un evento, el editor sugiere y comprueba sus variables, y solo ese evento ofrece el canal. Sin evento, el canal sirve para cualquiera.',
    'any-event' => 'Cualquier evento',
    'enabled' => 'Activado',
    'enabled-help' => 'Un canal desactivado no envía nada salvo pruebas.',
    'connection' => 'Conexión',
    'message' => 'Mensaje',
    'subject' => 'Asunto',
    'subject-help' => 'Una línea; las variables como {{form}} se sustituyen.',
    'template' => 'Plantilla',
    'template-help' => 'Variables entre llaves dobles: {{form}}. Basta con un idioma; un mensaje en un idioma que falta en la plantilla usa el idioma principal del sitio.',
    'history' => 'Historial',
    'token-help' => 'De @BotFather. Se guarda cifrado y no se vuelve a mostrar: escriba uno nuevo para sustituirlo.',
    'chats' => 'Chats',
    'chats-help' => 'Id de chats, o «Buscar chats» tras escribir al bot. Un destinatario del evento es la dirección que trae el evento.',
    'mode-help' => 'El correo del sitio es el configurado para todo el sitio.',
    'from-help' => 'Vacío: el remitente del sitio.',
    'emails' => 'Destinatarios',
    'emails-help' => 'Administradores, direcciones o la dirección que trae el evento: el visitante que rellenó el formulario.',
    'credentials' => 'Secretos',
    'options' => 'Ajustes',
    'recipients' => 'Destinatarios',
];
