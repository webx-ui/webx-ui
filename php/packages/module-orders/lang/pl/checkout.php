<?php

declare(strict_types=1);

/*
 * The checkout (spec §33). The words of the page, its steps and the «Thanks» come with their
 * stages; the tab «Checkout» of the settings is here now.
 */
return [
    'settings' => [
        'tab' => 'Składanie zamówienia',
        'fields' => 'Dane kontaktowe kupującego',
        'fields-help' => 'Imię jest wymagane zawsze. Zamówienie potrzebuje telefonu lub e-maila, więc co najmniej jedno z nich pozostaje wymagane.',
        'last-name' => 'Nazwisko',
        'phone' => 'Telefon',
        'email' => 'E-mail',
        'recipient' => '„Odbiorca to inna osoba”',
        'recipient-help' => 'Pole wyboru pod danymi kontaktowymi dla zamówienia, które odbierze ktoś inny.',
        'comment' => 'Komentarz',
        'comment-help' => 'Pole, w którym kupujący zostawia komentarz do zamówienia.',
        'terms-card' => 'Warunki',
        'terms' => 'Akceptacja warunków',
        'terms-help' => 'Informacja — wiersz pod przyciskiem; pole wyboru — kupujący zaznacza je przed złożeniem zamówienia. Akceptacja trafia do historii zamówienia.',
        'terms-page' => 'Strona z warunkami',
        'terms-page-help' => 'Strona witryny lub adres, do którego prowadzi akceptacja.',
        'privacy-page' => 'Polityka prywatności',
        'privacy-page-help' => 'Drugi dokument, jeśli witryna go ma: jak przetwarzane są dane osobowe.',
        'contact-needed' => 'Zostaw telefon lub e-mail jako wymagany: zamówienie potrzebuje jednego z nich.',
        'values' => [
            'required' => 'Wymagane',
            'optional' => 'Opcjonalne',
            'hidden' => 'Ukryte',
            'on' => 'Wł.',
            'off' => 'Wył.',
            'none' => 'Brak',
            'notice' => 'Informacja',
            'checkbox' => 'Pole wyboru',
        ],
    ],
];
