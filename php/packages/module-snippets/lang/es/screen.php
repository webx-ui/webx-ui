<?php

declare(strict_types=1);

return [
    'name' => 'Nombre',
    'name-help' => 'Solo para administradores, nunca aparece en el sitio.',
    'place' => 'Lugar',
    'place-help' => 'Dónde se imprime el código en la página. Los lugares propios se declaran en config/webx-snippets.php.',
    'code' => 'Código',
    'code-help' => 'Pegue exactamente lo que dio el servicio, sin <html>, <head> ni <body>. document.write no funciona en un código que espera el consentimiento.',
    'settings' => 'Ajustes',
    'enabled' => 'Activado',
    'enabled-help' => 'Un código activado está en el sitio en cuanto se guarda.',
    'consent' => 'Consentimiento de cookies',
    'consent-help' => 'Todo salvo «Necesarias» se imprime solo después de que el visitante acepte esa categoría.',
    'on-stage' => 'También en dev y stage',
    'on-stage-help' => 'Desactivado: el código se imprime solo en producción, una copia del sitio no cuenta visitas.',
    'coverage' => 'Dónde se imprime',
    'only-paths' => 'Solo en',
    'only-paths-help' => 'Rutas sin el idioma, * para cualquier cosa: /, /blog/*. Vacío es todo el sitio.',
    'except-paths' => 'Excepto',
    'except-paths-help' => 'Rutas donde el código nunca se imprime, como /blog/drafts/*.',
    'locales' => 'Idiomas',
    'locales-help' => 'Nada marcado es todos los idiomas.',
    'history' => 'Historial',
    'position' => 'Orden',
];
