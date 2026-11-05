<?php

declare(strict_types=1);

return [
    'currency' => 'Un feed usa la moneda del sitio: :currency.',
    'currency-unset' => 'El sitio no tiene moneda: defina WEBX_CATALOG_CURRENCY.',
    'filter-facet' => 'El filtro del feed nombra [:facet], que el sitio no tiene.',
    'source-property' => 'El feed toma el MPN de la propiedad [:property], que el sitio no tiene.',
    'no-item' => 'El feed no tiene plantilla de producto.',
    'template' => 'La plantilla no se puede leer: :reason',
    'too-large' => 'El archivo supera el tamaño permitido de un feed.',
    'stale' => 'La generación dejó de avanzar y se cerró.',
    'file-xml' => 'El archivo no es XML válido, línea :line: :reason',
    'file-json' => 'La cabecera y el pie no forman JSON válido: :reason',
    'file-json-item' => 'El producto :product no forma JSON válido: :reason',
    'file-csv' => 'La línea :line del archivo tiene :found campos en lugar de :expected.',
];
