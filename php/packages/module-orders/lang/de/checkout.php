<?php

declare(strict_types=1);

/*
 * The checkout (spec §33). The words of the page, its steps and the «Thanks» come with their
 * stages; the tab «Checkout» of the settings is here now.
 */
return [
    'settings' => [
        'tab' => 'Checkout',
        'fields' => 'Kontaktdaten des Käufers',
        'fields-help' => 'Der Vorname wird immer abgefragt. Eine Bestellung braucht Telefon oder E-Mail, daher bleibt mindestens eines davon Pflicht.',
        'last-name' => 'Nachname',
        'phone' => 'Telefon',
        'email' => 'E-Mail',
        'recipient' => '«Der Empfänger ist eine andere Person»',
        'recipient-help' => 'Ein Kästchen unter den Kontaktdaten für eine Bestellung, die jemand anderes abholt.',
        'comment' => 'Kommentar',
        'comment-help' => 'Ein Feld für einen Kommentar des Käufers zur Bestellung.',
        'terms-card' => 'Bedingungen',
        'terms' => 'Zustimmung zu den Bedingungen',
        'terms-help' => 'Hinweis — eine Zeile unter der Schaltfläche; Kästchen — der Käufer hakt es vor der Bestellung an. Die Zustimmung wird im Verlauf der Bestellung festgehalten.',
        'terms-page' => 'Seite mit den Bedingungen',
        'terms-page-help' => 'Eine Seite der Website oder eine Adresse, auf die die Zustimmung verlinkt.',
        'privacy-page' => 'Datenschutzerklärung',
        'privacy-page-help' => 'Ein zweites Dokument, falls die Website eines hat: wie personenbezogene Daten verarbeitet werden.',
        'contact-needed' => 'Lassen Sie Telefon oder E-Mail als Pflichtfeld: Eine Bestellung braucht eines davon.',
        'values' => [
            'required' => 'Pflicht',
            'optional' => 'Optional',
            'hidden' => 'Ausgeblendet',
            'on' => 'An',
            'off' => 'Aus',
            'none' => 'Keine',
            'notice' => 'Hinweis',
            'checkbox' => 'Kästchen',
        ],
    ],
];
