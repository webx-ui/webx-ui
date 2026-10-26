<?php

declare(strict_types=1);

return [
    'schema' => [
        'invalid' => 'El esquema no es un objeto JSON.',
        'version' => 'Versión de esquema desconocida: este módulo lee la versión 1.',
    ],
    'currency' => [
        'invalid' => 'La moneda es un código ISO 4217 de tres letras mayúsculas, como UAH o EUR.',
    ],
    'pricing' => [
        'round' => 'El paso de redondeo es un número mayor que 0.',
    ],
    'discount' => [
        'percent' => 'El descuento es un porcentaje mayor que 0 y menor que 100.',
        'until' => 'El último día del descuento es una fecha AAAA-MM-DD.',
    ],
    'steps' => [
        'empty' => 'Un configurador necesita al menos un paso.',
    ],
    'key' => [
        'invalid' => 'La clave «:key» no es a-z, 0-9 y _ empezando por una letra.',
        'duplicate' => 'La clave «:key» está ocupada: pasos y campos de medidas comparten claves.',
    ],
    'type' => [
        'unknown' => 'Tipo de paso desconocido: cards, swatches, buttons o dimensions.',
    ],
    'options' => [
        'empty' => 'Un paso de elección necesita al menos una opción.',
    ],
    'fields' => [
        'empty' => 'Un paso de medidas necesita al menos un campo.',
    ],
    'value' => [
        'invalid' => 'El valor «:value» no es un slug: a-z, 0-9, - y _.',
        'duplicate' => 'El valor «:value» aparece dos veces en este paso.',
    ],
    'label' => [
        'missing' => 'Hace falta un nombre en el idioma predeterminado (:locale).',
    ],
    'text' => [
        'invalid' => 'Un texto es una cadena o un conjunto de idiomas.',
    ],
    'image' => [
        'missing' => 'Elija una imagen de la mediateca.',
        'unknown' => 'La mediateca no tiene el archivo «:key».',
    ],
    'swatch' => [
        'invalid' => 'Una muestra es #rgb, #rrggbb o un degradado de colores hex.',
    ],
    'price' => [
        'invalid' => 'Un precio es un número.',
        'per_m2' => 'Un precio por m² necesita superficie: dé a las opciones de un paso una fórmula de superficie.',
    ],
    'area' => [
        'main_count' => 'Solo las opciones de un paso pueden tener fórmula de superficie.',
        'missing' => 'Cada opción del paso principal necesita una fórmula de superficie.',
        'syntax' => 'La fórmula falla en el carácter :position: :reason.',
        'unknown_key' => 'La fórmula usa «:key», que no es un campo de medidas.',
    ],
    'range' => [
        'invalid' => 'El rango es incorrecto: min mayor que max o un paso no mayor que 0.',
    ],
    'limits' => [
        'unknown_field' => 'limits nombra «:key», que no es un campo de medidas.',
    ],
    'defaults' => [
        'unknown_field' => 'defaults nombra «:key», que no es un campo de medidas.',
    ],
    'condition' => [
        'invalid' => 'Una condición es un paso y los valores con los que se elige.',
        'unknown_step' => 'No hay ningún paso «:step».',
        'forward' => 'El paso «:step» no está por encima de este: una condición solo mira hacia arriba.',
        'not_choice' => 'El paso «:step» es de medidas: una condición nombra un paso con opciones.',
        'unknown_value' => 'El paso «:step» no tiene la opción «:value».',
    ],
    'initial' => [
        'unknown' => 'La elección inicial «:key» no es un paso, un campo ni uno de sus valores.',
    ],
    'previews' => [
        'condition' => 'Una regla de imagen nombra un paso o un valor que no existe.',
    ],
    'values' => [
        'range' => 'La medida «:key» va de :min a :max.',
        'number' => 'La medida «:key» es un número.',
    ],
];
