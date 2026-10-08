<?php

declare(strict_types=1);

return [
    'no-marker' => 'No hay data-wx-block en la raíz: el script no se ejecutará y el panel no podrá resaltar el bloque en la vista previa.',
    'stray-selectors' => 'Selectores fuera del prefijo del bloque .b-:slug: :selectors',
    'bare-selectors' => 'Los selectores de elemento alcanzan todo el sitio: :selectors',
    'media-query' => '@media mide la ventana. Un bloque se dimensiona por su contenedor: use @container.',
    'string-on-text' => 'Con un shortcode dentro, :field es HTML: una función de cadena o un cast entrega ese HTML a {{ }}, que lo escapa por segunda vez. Cámbielo a través de wx_text(): {{ wx_text(:field)->trimEnd(".") }} — trim, trimStart, stripPrefix, stripSuffix y map() lo mantienen como HTML.',
    'variables-missing' => 'La plantilla usa :variables, que el esquema no declara. La publicación será rechazada.',
    'ok-marker' => 'La raíz lleva data-wx-block.',
    'ok-prefix' => 'Todos los selectores empiezan por .b-:slug.',
    'ok-bare' => 'No hay selectores de elemento sueltos.',
    'ok-container' => 'El ancho lo deciden las consultas de contenedor.',
    'ok-variables' => 'Todas las variables de la plantilla son campos del esquema.',
    'blocks' => [
        'stray_values' => [
            'title' => 'Valores de bloque para campos que el tipo no tiene',
            'found' => 'Hay bloques con valores de campos que su tipo no define, restos de una importación o de un campo quitado del tipo.',
            'why' => 'El visitante no los ve, pero están en los datos del editor y en lo que lee un agente, y aparecen como una etiqueta equivocada del bloque.',
            'fix' => 'Quítelos con la corrección o en todo el sitio con php artisan webx:blocks:prune. Un bloque de un tipo que ya no existe no se toca. Los elementos de un repetidor se comparan con sus campos.',
        ],
        'unknown_shortcodes' => [
            'title' => 'Shortcodes mal escritos',
            'found' => 'Un texto tiene un corchete que casi es un shortcode del sitio, o que lleva argumentos, y no lo es.',
            'why' => 'Solo se reemplazan los shortcodes registrados. Lo demás se imprime tal cual, con corchetes, a la vista de todos los visitantes.',
            'fix' => 'Corrija el nombre al sugerido o escriba [[nombre]] si la página debe mostrar los corchetes. La lista está en la ayuda de shortcodes del campo y en «Ajustes» → «Shortcodes».',
        ],
        'hardcoded_values' => [
            'title' => 'Valores en lugar de un shortcode',
            'found' => 'Un texto contiene un teléfono, un correo u otro valor que ya guarda un shortcode de «Ajustes» → «Shortcodes».',
            'why' => 'Hoy es correcto y deja de serlo el día que cambie: el shortcode cambia en todas partes, un valor escrito a mano solo donde alguien lo recuerde.',
            'fix' => 'Sustituya el valor por el shortcode sugerido, p. ej. [phone]. El enlace también lo crea el shortcode.',
        ],
    ],
    'syntax' => 'La plantilla no compila: :reason. La publicación será rechazada.',
    'unknown-field-type' => 'Campos de un tipo que el sitio no conoce: :fields. El formulario muestra un aviso en su lugar y nadie comprueba sus valores.',
    'field-id' => 'El id de un campo son letras, dígitos, _ y -, empezando por una letra: :ids.',
    'marker-slug' => 'La raíz está marcada con data-wx-block=":marker", pero el identificador es «:slug»: el script y el panel encuentran el bloque por el identificador exacto. La publicación será rechazada.',
];
