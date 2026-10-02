<?php

declare(strict_types=1);

return [
    'on' => 'Activado',
    'normalise-note' => 'Funciona para las peticiones que el servidor web entrega al sitio. Si el hallazgo sigue tras la próxima ejecución, el servidor web responde esa dirección por sí mismo: configura allí la redirección.',
    'robots-file-note' => 'El sitio tiene un archivo public/robots.txt. El servidor web lo sirve antes de preguntar al sitio, así que elimínalo para que el ajuste surta efecto.',
    'redirect-chain' => 'Saltos antes de la página: :steps',
    'title-duplicate' => '“:title” — en :count fichas',
    'redirect-broken' => ':target responde :status',
    'rule-dead' => 'La dirección responde :status',
];
