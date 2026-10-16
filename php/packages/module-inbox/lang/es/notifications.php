<?php

declare(strict_types=1);

// The event `inbox.submission` of webx-ui/module-notifications: its name and the values its templates get.
return [
    'event' => 'Un envío de un formulario',
    'vars' => [
        'form' => 'El nombre del formulario',
        'form_slug' => 'El slug del formulario',
        'submission_id' => 'El número del envío',
        'submission_url' => 'El envío en el panel',
        'created_at' => 'Cuándo llegó',
        'fields' => 'Todas las respuestas, una por línea: «Etiqueta: valor»',
        'answers' => 'Las respuestas, como lista',
        'answer_name' => 'El nombre interno del campo',
        'answer_label' => 'La etiqueta del campo',
        'answer_value' => 'La respuesta',
        'field' => 'Una respuesta, por el nombre interno de su campo',
        'files' => 'Los nombres de los archivos adjuntos',
        'page' => 'La página desde la que se envió el formulario',
        'locale' => 'El idioma del envío',
        'utm' => 'Etiquetas UTM',
        'reply_to' => 'El e-mail del visitante, del campo de e-mail del formulario',
    ],
];
