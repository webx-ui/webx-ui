<?php

declare(strict_types=1);

/*
 * The checkout (spec §33). The words of the page, its steps and the «Thanks» come with their
 * stages; the tab «Checkout» of the settings is here now.
 */
return [
    'settings' => [
        'tab' => 'Checkout',
        'fields' => 'Datos de contacto del comprador',
        'fields-help' => 'El nombre se pide siempre. Un pedido necesita un teléfono o un e-mail, así que al menos uno de ellos sigue siendo obligatorio.',
        'last-name' => 'Apellidos',
        'phone' => 'Teléfono',
        'email' => 'E-mail',
        'recipient' => '«El destinatario es otra persona»',
        'recipient-help' => 'Una casilla bajo los datos de contacto para un pedido que recoge otra persona.',
        'comment' => 'Comentario',
        'comment-help' => 'Un campo donde el comprador deja un comentario sobre el pedido.',
        'terms-card' => 'Condiciones',
        'terms' => 'Aceptación de las condiciones',
        'terms-help' => 'Aviso — una línea bajo el botón; casilla — el comprador la marca antes de hacer el pedido. La aceptación se guarda en el historial del pedido.',
        'terms-page' => 'Página de condiciones',
        'terms-page-help' => 'Una página del sitio o una dirección a la que enlaza la aceptación.',
        'privacy-page' => 'Política de privacidad',
        'privacy-page-help' => 'Un segundo documento, si el sitio lo tiene: cómo se tratan los datos personales.',
        'contact-needed' => 'Deja el teléfono o el e-mail como obligatorio: un pedido necesita uno de ellos.',
        'values' => [
            'required' => 'Obligatorio',
            'optional' => 'Opcional',
            'hidden' => 'Oculto',
            'on' => 'Sí',
            'off' => 'No',
            'none' => 'Ninguna',
            'notice' => 'Aviso',
            'checkbox' => 'Casilla',
        ],
    ],
];
