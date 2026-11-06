<?php

declare(strict_types=1);

return [
    'template-unclosed' => 'La sección {{#:section}} no está cerrada: termínela con {{/:section}}.',
    'template-unexpected' => '{{/:section}} cierra una sección que nunca se abrió.',
    'template-tag' => 'La etiqueta {{:tag no está cerrada: termínela con }}.',
    'template-name' => '{{:name}} no es un nombre de variable: minúsculas, dígitos y _, como mucho un punto.',
    'template-partial' => 'Los parciales ({{>name}}) no están disponibles en una plantilla de mensaje.',
    'template-delimiters' => 'Cambiar los delimitadores ({{=<% %>=}}) no está disponible en una plantilla de mensaje.',
    'template-pragma' => 'Los pragmas y bloques ({{%…}}, {{$…}}) no están disponibles en una plantilla de mensaje.',
    'template-cdata' => 'Una variable no puede estar dentro de un bloque CDATA literal: {{:name}} se escaparía dos veces. Quite el CDATA que la rodea y use la variable _cdata, por ejemplo {{description_cdata}}.',
];
