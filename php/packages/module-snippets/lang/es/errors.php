<?php

declare(strict_types=1);

return [
    'required' => 'El campo «:field» no puede estar vacío.',
    'name-long' => 'El nombre supera los :max caracteres.',
    'code-long' => 'El código ocupa :bytes bytes; el límite es :max.',
    'consent' => 'La categoría de consentimiento es una de: :categories.',
    'mask' => 'Una ruta empieza en la raíz, como «/blog/*», sin la dirección del sitio ni «?»: [:mask] no lo es.',
    'masks-many' => 'No más de :max rutas en una lista.',
    'locales' => 'El sitio no se publica en :unknown. Tiene: :known.',
    'boolean' => 'El campo «:field» es sí o no.',
    'list' => 'Se espera una lista de rutas o idiomas.',
    'place-undeclared' => 'El lugar «:place» no está declarado en config/webx-snippets.php.',
    'place-orphaned' => 'El lugar «:place» ya no está declarado y el código no se imprimiría en ninguna parte: muévalo o desactívelo.',
    'document-tags' => 'El código tiene :tags: pegue solo lo que el servicio pide poner dentro de la página.',
    'unknown' => 'No hay ningún código con el id :ids.',
    'reorder' => 'El orden es un lugar y la lista de sus códigos.',
];
