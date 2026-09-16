<?php

declare(strict_types=1);

return [
    'title' => 'nombre',
    'parent_id' => 'carpeta principal',
    'directory_id' => 'carpeta',
    'files' => 'archivos',
    'item' => 'Archivo :number: :message',
    'shape' => 'Esto no es un archivo de la biblioteca.',
    'accept' => 'Este campo solo acepta :kind.',
    'localized' => 'Un campo de archivos no se traduce; sí se traducen los pies de foto que contiene.',
    'kind' => [
        'image' => 'imágenes',
        'video' => 'vídeos',
        'audio' => 'archivos de audio',
        'document' => 'documentos',
        'other' => 'otros archivos',
    ],
];
