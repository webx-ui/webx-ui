<?php

declare(strict_types=1);

return [
    'media' => [
        'missing_file' => [
            'title' => 'Archivos de la biblioteca que faltan en el disco',
            'found' => 'La biblioteca de medios lista un archivo que el disco no tiene.',
            'why' => 'Cada página y campo que lo usa muestra una imagen rota o un enlace de descarga muerto.',
            'fix' => 'Sube el archivo de nuevo en la biblioteca de medios, o copia la carpeta storage del lugar de donde viene el sitio.',
        ],
        'heavy' => [
            'title' => 'Imágenes demasiado pesadas para una página',
            'found' => 'Hay imágenes en la biblioteca de medios que pesan más que el límite.',
            'why' => 'Una página que muestra una carga despacio en el móvil, y los buscadores posicionan peor las páginas lentas.',
            'fix' => 'Sustitúyelas por versiones más pequeñas: una foto para una página rara vez necesita más de 2000 píxeles de ancho ni pesar más de unos cientos de kilobytes.',
        ],
        'orphan_thumbs' => [
            'title' => 'Vistas previas de archivos eliminados',
            'found' => 'El disco guarda carpetas de vistas previas de archivos que la biblioteca de medios ya no tiene.',
            'why' => 'Ocupan espacio y nada las mostrará nunca.',
            'fix' => 'Elimínelas con el botón de aquí o con php artisan webx:media:prune-thumbs.',
        ],
    ],
];
