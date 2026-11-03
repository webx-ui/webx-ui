<?php

declare(strict_types=1);

// The notices of payments (spec §11.5): the values it adds to the order's events and the manager's
// `payments.problem` with its default template; the site's settings tab that chooses its channels.
return [
    'problem' => 'Problem z płatnością',
    'vars' => [
        'payment_url' => 'Link do opłacenia zamówienia',
        'payment_instructions' => 'Dane do przelewu',
        'payment_method' => 'Metoda płatności',
        'number' => 'Numer zamówienia',
        'order_url' => 'Zamówienie w panelu',
        'problem' => 'Co się stało',
        'amount' => 'Kwota',
        'method' => 'Metoda płatności',
        'gateway_ref' => 'ID płatności w bramce',
        'message' => 'Szczegóły',
    ],
    'sample' => [
        'subject' => 'Problem z płatnością: zamówienie {{number}}',
        'template' => "{{problem}} — zamówienie {{number}}, {{amount}}, {{method}}.\n{{message}}\nID płatności: {{gateway_ref}}\n{{order_url}}",
    ],
    'settings' => [
        'tab' => 'Płatności',
        'card' => 'Powiadomienia',
        'help' => 'Kogo powiadomić, gdy pieniądze trafią nie tam, gdzie trzeba: podwójna płatność, zła kwota, pieniądze za anulowane zamówienie, kończąca się blokada środków, odrzucone pobranie.',
    ],
];
