<?php

declare(strict_types=1);

return [
    'title' => 'Índice de búsqueda',
    'lead' => 'El catálogo busca, filtra y cuenta con Manticore. Cuando no responde, el panel responde desde la base de datos.',
    'refresh' => 'Actualizar',
    'load-failed' => 'No se pudo cargar el estado del índice.',

    'connection' => 'Servidor',
    'address' => 'Dirección',
    'prefix' => 'Prefijo de tablas',
    'version' => 'Versión',
    'available' => 'Responde',
    'unavailable' => 'No responde',

    'tables' => 'Tablas',
    'language' => 'Idioma',
    'table' => 'Tabla',
    'in-index' => 'En el índice',
    'in-database' => 'En la base de datos',
    'state' => 'Estado',
    'state-ready' => 'Al día',
    'state-stale' => 'Esquema desactualizado',
    'state-missing' => 'No existe',
    'filling' => 'Una reconstrucción está llenando la tabla de al lado.',
    'filled' => 'Una reconstrucción está llenando la tabla de al lado: :done de :total',
    'no-tables' => 'El servidor no dijo qué tablas tiene.',

    'queue' => 'Cola',
    'waiting' => 'Esperando a escribirse',
    'oldest' => 'La más antigua desde',
    'queue-empty' => 'Todos los productos guardados están en el índice.',

    'outdated' => 'El índice no tiene el esquema que el catálogo escribe ahora',
    'outdated-text' => 'Mientras tanto la tienda responde desde las tablas antiguas, pero lo que les falta — un filtro nuevo, códigos — no se busca. Una reconstrucción llena tablas nuevas junto a las activas y las intercambia; en un catálogo grande son minutos de carga, así que elige el momento.',
    'rebuild' => 'Reconstruir',
    'rebuild-again' => 'Reconstruir de nuevo',
    'rebuild-title' => '¿Reconstruir el índice de búsqueda?',
    'rebuild-text' => 'Todos los productos se escriben de nuevo en tablas nuevas, que luego sustituyen a las activas. Mientras tanto la tienda sigue funcionando con las tablas antiguas.',
    'rebuild-busy' => 'Ya hay una reconstrucción en curso.',
    'rebuild-locked' => 'Hay otra reconstrucción en curso — se inició desde la consola. Inicia esta cuando termine.',
    'rebuild-console' => 'Hay en curso una reconstrucción iniciada desde la consola: el botón vuelve cuando termine.',
    'rebuild-queued' => 'Esperando al worker de la cola',
    'rebuild-running' => 'Reconstruyendo: :done de :total',
    'rebuild-done' => 'Reconstruido: :done productos',
    'rebuild-failed' => 'La reconstrucción falló',
    'rebuild-stalled' => 'No se sabe nada de la reconstrucción desde hace un cuarto de hora: el worker de la cola no está en marcha o se detuvo. Iníciala de nuevo cuando el worker funcione.',
    'rebuild-started' => 'La reconstrucción está en la cola.',
    'cancel' => 'Cancelar',
];
