<?php

declare(strict_types=1);

/*
 * A field that points at records of another section (`wx-relations`), and the same choice as a
 * block's filter ("only related to" in `wx-collection`). The keys are the panel's: the English
 * here is what `messages.ts` carries.
 */

return [
    'field-add' => 'Añadir…',
    'field-searching' => 'Buscando…',
    'field-nothing' => 'No se ha encontrado nada.',
    'field-empty' => 'Aún no hay nada elegido.',
    'field-remove' => 'Quitar',
    'field-drag' => 'Arrastre para cambiar el orden',
    'field-hidden' => 'No está en el sitio',
    'field-trashed' => 'En la papelera',
    'field-missing' => 'No encontrado',
    'field-full' => 'No se pueden elegir más de :max.',
    'field-forbidden' => 'No puede ver estos registros, así que aquí no se puede cambiar la selección.',
    'collection-related' => 'Solo relacionados con',
    'collection-related-to' => 'Solo relacionados con «:target»',
    'collection-related-type' => 'Qué sección',
    'collection-related-any' => 'Sin acotar: todos los registros, relacionados o no.',
    'collection-related-current' => 'El registro de la página donde está',
    'collection-related-current-hint' => 'En la página de un registro de «:target», el bloque muestra lo relacionado con él; en cualquier otra página no muestra nada.',
];
