<?php

declare(strict_types=1);

// The regions of the layout (the regions spec): the words a declaration may point at with
// `trans::webx-blocks::regions.header`, and what the server says about a region.
return [
    'header' => 'Cabecera',
    'footer' => 'Pie de página',
    'usage' => 'Zona «:title»',
    'preview-failed' => 'Un bloque de esta zona falla. En el sitio toda la zona mostrará en su lugar el marcado del código.',
    'not-in-registry' => ':path no es una página del registro de direcciones del sitio, así que la zona se muestra en una página vacía de la plantilla.',
    'too-many' => 'La zona admite como máximo :max bloques.',
    'not-allowed' => 'El bloque «:type» no se puede colocar en esta zona.',
    'refused' => 'La zona no acepta estos bloques.',
    'conflict' => 'La zona cambió desde que la abriste. Recárgala para ver los cambios.',
    'failed-block' => 'El bloque «:type» (:key) falla: :reason',
    'not-published' => 'No publicado: un bloque del borrador no se puede dibujar.',
    'nothing-to-publish' => 'La zona nunca se guardó: no hay nada que publicar.',
    'no-version' => 'La zona no tiene la versión :number.',
    'no-fallback' => 'La plantilla aún no ha nombrado una vista para esta zona, o la vista ya no existe.',
    'adopt-taken' => 'Ya existe un tipo de bloque «:slug».',
    'adopt-failed' => 'El marcado no pudo convertirse en un tipo de bloque.',
    'adopt-forbidden' => 'Mover el marcado a un tipo de bloque requiere el derecho de editar tipos de bloque.',
];
