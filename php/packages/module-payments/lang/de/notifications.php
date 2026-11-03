<?php

declare(strict_types=1);

// The notices of payments (spec §11.5): the values it adds to the order's events and the manager's
// `payments.problem` with its default template; the site's settings tab that chooses its channels.
return [
    'problem' => 'Zahlungsproblem',
    'vars' => [
        'payment_url' => 'Link zur Zahlung der Bestellung',
        'payment_instructions' => 'Bankverbindung für die Zahlung',
        'payment_method' => 'Zahlungsart',
        'number' => 'Bestellnummer',
        'order_url' => 'Bestellung im Panel',
        'problem' => 'Was ist passiert',
        'amount' => 'Betrag',
        'method' => 'Zahlungsart',
        'gateway_ref' => 'Zahlungs-ID beim Anbieter',
        'message' => 'Details',
    ],
    'sample' => [
        'subject' => 'Zahlungsproblem: Bestellung {{number}}',
        'template' => "{{problem}} — Bestellung {{number}}, {{amount}}, {{method}}.\n{{message}}\nZahlungs-ID: {{gateway_ref}}\n{{order_url}}",
    ],
    'settings' => [
        'tab' => 'Zahlungen',
        'card' => 'Benachrichtigungen',
        'help' => 'Wer informiert wird, wenn Geld falsch ankommt: doppelt bezahlt, falscher Betrag, Geld für eine stornierte Bestellung, eine Reservierung läuft ab, eine Abbuchung wurde abgelehnt.',
    ],
];
