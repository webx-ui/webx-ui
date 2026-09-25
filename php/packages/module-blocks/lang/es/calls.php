<?php

declare(strict_types=1);

return [
    'kind' => 'Un tipo es un bloque o un componente.',
    'kind-in-use' => 'El bloque está en páginas y no puede convertirse en componente. Páginas: :count.',
    'unknown-call' => '«:type» no es un tipo de bloque: la llamada no imprime nada en el sitio.',
    'dynamic-call' => 'El tipo llamado no está escrito literalmente: publicarlo no comprobará esta plantilla.',
    'delete-used-by' => 'Otros tipos llaman a este, y sus plantillas dejarían un hueco. Quite primero las llamadas.',
    'delete-used-by-one' => 'Llamado por «:title» (:slug)',
    'publish-cycle' => 'Los tipos se llaman entre sí en círculo: :path.',
    'publish-breaks-parent' => 'Rompe «:parent» en su ejemplo: :reason',
    'publish-breaks-parent-on' => 'Rompe «:parent» en «:entity»: :reason',
    'publish-breaks-declared' => 'Rompe el lugar desde el que lo llama el módulo :module: :reason',
    'customise-exists' => 'Ya existe un tipo con este identificador.',
    'customised-from' => 'Desde :view',
];
