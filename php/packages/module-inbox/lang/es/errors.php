<?php

declare(strict_types=1);

return [
    'refused' => 'No se ha podido enviar el formulario. Inténtelo de nuevo en un momento.',
    'enclosure' => 'Lo que se adjuntó a este formulario no se puede enviar con él. Recargue la página e inténtelo de nuevo.',
    'captcha' => 'Confirme que no es un robot y envíe el formulario de nuevo.',
    'captcha-script' => 'Este formulario comprueba con JavaScript que no es un robot. Actívelo y envíe el formulario de nuevo.',
    'captcha-unavailable' => 'No pudimos comprobar que no es un robot. Recargue la página o pruebe con otro navegador.',
    'too-many' => 'Demasiados envíos desde esta dirección. Inténtelo de nuevo dentro de un minuto.',
    'form-has-submissions' => 'El formulario «:form» tiene envíos, así que se puede desactivar pero no eliminar.',
    'status-in-use' => 'Todavía hay envíos en el estado «:status», así que no se puede eliminar.',
    'no-statuses' => 'No hay ningún estado que dar a un envío nuevo. Ejecute las migraciones.',
    'file-missing' => 'Este archivo ya no está aquí.',

    'slug-empty' => 'Una dirección necesita letras o cifras, por ejemplo «contact-us».',
    'field-name-empty' => 'Un nombre necesita letras o cifras, por ejemplo «phone».',
    'status-key-empty' => 'Una clave necesita letras o cifras, por ejemplo «in-progress».',
    'recipient-shape' => 'El destinatario :entry no es ni un administrador ni una dirección de correo.',
    'recipient-unknown' => 'El destinatario :entry nombra a un administrador que no existe.',
    'choices-required' => 'Un campo de lista necesita al menos una opción con un valor.',
    'email-field' => 'El campo de respuesta tiene que ser un campo de correo de este formulario.',
    'no-such-answer' => 'Este envío no tiene ninguna respuesta con ese nombre.',
    'not-correctable' => 'Un archivo o un consentimiento no se pueden corregir.',
    'nobody-to-notify' => 'El formulario «:form» no indica a quién escribir.',
    'condition-unknown-field' => 'La condición «:key» de «:field» nombra «:name», un campo que este formulario no tiene (o que este cambio elimina).',
    'condition-not-choice' => 'La condición «:key» de «:field» nombra «:name», que no es una elección: solo una lista, botones de opción, casillas o un consentimiento de arriba sirven de condición.',
    'condition-not-before' => 'La condición «:key» de «:field» nombra «:name», que no está por encima. Una condición nombra solo un campo de arriba.',
    'condition-unknown-value' => 'La condición «:key» de «:field» espera el valor «:value» de «:name», que ese campo no ofrece.',
    'field-has-dependents' => 'El campo «:field» se nombra en las condiciones de :fields. Cambie primero esas condiciones.',
    'step-empty' => 'El paso :field no tiene ningún campo después. Ponga un campo debajo o elimínelo.',
    'step-too-many' => 'Un formulario tiene como máximo :max pasos.',
    'step-unknown' => 'El formulario ha cambiado desde que se abrió la página. Recargue la página.',
    'datetime-range' => 'El día no tiene lugar para ninguna hora: «Desde» y «Hasta» como HH:MM, y «Desde» al menos un paso antes que «Hasta».',
    'datetime-step' => 'El paso de las horas es de 15, 20, 30, 60, 90 o 120 minutos.',
    'datetime-weekdays' => 'Cada día de la semana una vez, del 1 (lunes) al 7 (domingo).',
];
