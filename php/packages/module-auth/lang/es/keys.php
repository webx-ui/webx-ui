<?php

declare(strict_types=1);

/*
 * Keys for programs: issued under Administrators → Keys, shown once, revoked there. The words
 * are about a key a program holds, not about tokens and scopes underneath — except the scopes
 * themselves, which are kept as issued because an agent's refusal quotes them.
 */

return [
    'title' => 'Claves',
    'issue' => 'Emitir una clave',
    'issue-short' => 'Emitir',
    'lead' => 'Una clave permite que un programa — un vigilante de la cola, una importación nocturna — trabaje en este panel sin navegador. Actúa como el administrador que elija y nunca puede hacer más que él. Una clave es un programa, no una persona: lo que un módulo reserva a una persona, como una etapa revisada a mano, no lo puede hacer.',
    'name' => 'Nombre',
    'name-hint' => 'Qué es el programa, para que la lista lo diga: «vigilante de la cola», «importación nocturna».',
    'who' => 'Actúa como',
    'who-hint' => 'Todo lo que la clave cambie se hace en nombre de este administrador y dentro de sus permisos.',
    'allowed' => 'Qué se permite',
    'everything' => 'Todo lo que puede el administrador',
    'chosen' => 'Solo los scopes elegidos abajo',
    'scopes' => 'Scopes',
    'scopes-hint' => 'Un módulo instalado más tarde queda fuera de alcance hasta que una clave nueva lo nombre.',
    'scopes-required' => 'Elija al menos un scope.',
    'lifetime' => 'Vigencia',
    'lifetime-hint' => 'Una clave no se puede prorrogar: emita una nueva antes de que esta caduque y revoque la anterior.',
    'days-30' => '30 días',
    'days-90' => '90 días',
    'days-365' => '1 año',
    'days-1095' => '3 años',
    'days-3650' => '10 años',
    'issued-title' => 'Copie la clave ahora',
    'issued-text' => 'Esta es la única vez que se muestra la clave. Nadie podrá volver a verla, ni aquí ni en el servidor. Si se pierde, revóquela y emita otra.',
    'secret' => 'Clave',
    'copy' => 'Copiar',
    'copied' => 'Copiada',
    'copy-failed' => 'No se pudo acceder al portapapeles. Seleccione la clave y cópiela a mano.',
    'address' => 'Dirección del servidor',
    'header-hint' => 'El programa envía la clave en una cabecera: Authorization: Bearer <clave>.',
    'done' => 'Listo',
    'cancel' => 'Cancelar',
    'expires' => 'Caduca',
    'until' => 'hasta',
    'last-used' => 'Último uso',
    'never-used' => 'Nunca',
    'issued-by' => 'Emitida por :name',
    'issued-console' => 'Emitida en un terminal',
    'full' => 'Todo lo que puede :name',
    'deleted' => 'Administrador n.º :id',
    'revoke' => 'Revocar',
    'revoked' => 'Revocada',
    'expired' => 'Caducada',
    'confirm-title' => '¿Revocar «:name»?',
    'confirm-text' => 'El programa que la usa será rechazado en su próxima llamada. Una clave revocada no se puede volver a activar.',
    'confirm' => 'Revocar',
    'revoked-done' => '«:name» está revocada',
    'empty' => 'No se ha emitido ninguna clave',
    'lead-mine' => 'Una clave permite a un programa — un vigilante de cola, una importación nocturna — trabajar en este panel como usted, sin navegador. Nunca puede más que usted, y solo en los ámbitos que elija.',
    'empty-mine' => 'Aún no tiene claves',

];
