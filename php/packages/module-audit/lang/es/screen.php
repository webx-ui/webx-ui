<?php

declare(strict_types=1);

return [
    'tab' => 'Auditoría',
    'base-url' => 'Dirección a auditar',
    'base-url-help' => 'Vacío significa APP_URL. La auditoría pide al sitio sus páginas en esta dirección.',
    'resolve-to' => 'Conectar a',
    'resolve-to-help' => 'Una dirección IP o un host al que abrir la conexión, manteniendo el nombre público en la petición. Vacío significa lo que responda el DNS. Para Docker y servidores tras NAT.',
    'other-hosts' => 'Otras direcciones de este sitio',
    'other-hosts-help' => 'Entornos de desarrollo, de pruebas y dominios antiguos, uno por línea. Un enlace a cualquiera de ellos es un error dondequiera que se encuentre.',
    'fix-unavailable' => 'Esta corrección ya no puede cerrar este hallazgo. Ejecuta la auditoría de nuevo.',
];
