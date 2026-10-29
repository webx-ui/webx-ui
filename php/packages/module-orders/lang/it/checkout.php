<?php

declare(strict_types=1);

/*
 * The checkout (spec §33). The words of the page, its steps and the «Thanks» come with their
 * stages; the tab «Checkout» of the settings is here now.
 */
return [
    'settings' => [
        'tab' => 'Checkout',
        'fields' => 'Contatti dell\'acquirente',
        'fields-help' => 'Il nome viene sempre chiesto. Un ordine ha bisogno di un telefono o di un\'e-mail, quindi almeno uno dei due resta obbligatorio.',
        'last-name' => 'Cognome',
        'phone' => 'Telefono',
        'email' => 'E-mail',
        'recipient' => '«Il destinatario è un\'altra persona»',
        'recipient-help' => 'Una casella sotto i contatti per un ordine che ritira qualcun altro.',
        'comment' => 'Commento',
        'comment-help' => 'Un campo in cui l\'acquirente lascia un commento all\'ordine.',
        'terms-card' => 'Condizioni',
        'terms' => 'Accettazione delle condizioni',
        'terms-help' => 'Avviso — una riga sotto il pulsante; casella — l\'acquirente la spunta prima di ordinare. L\'accettazione viene registrata nella cronologia dell\'ordine.',
        'terms-page' => 'Pagina delle condizioni',
        'terms-page-help' => 'Una pagina del sito o un indirizzo a cui rimanda l\'accettazione.',
        'privacy-page' => 'Informativa sulla privacy',
        'privacy-page-help' => 'Un secondo documento, se il sito ne ha uno: come vengono trattati i dati personali.',
        'contact-needed' => 'Lascia obbligatorio il telefono o l\'e-mail: un ordine ha bisogno di uno dei due.',
        'values' => [
            'required' => 'Obbligatorio',
            'optional' => 'Facoltativo',
            'hidden' => 'Nascosto',
            'on' => 'Sì',
            'off' => 'No',
            'none' => 'Nessuna',
            'notice' => 'Avviso',
            'checkbox' => 'Casella',
        ],
    ],
];
