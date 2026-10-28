<?php

declare(strict_types=1);

return [
    'menu' => [
        'label' => 'Moje konto',
        'profile' => 'Profil',
        'security' => 'Logowanie i bezpieczeństwo',
        'sign_out' => 'Wyloguj się',
    ],
    'profile' => [
        'title' => 'Profil',
        'personal' => 'Dane osobowe',
        'save' => 'Zapisz',
        'saved' => 'Zapisano.',
    ],
    'email' => [
        'title' => 'E-mail',
        'confirmed' => 'potwierdzony',
        'unconfirmed' => 'niepotwierdzony',
        'pending' => 'Czeka na potwierdzenie: :email — kliknij link w wiadomości.',
        'submit' => 'Zmień e-mail',
    ],
    'security' => [
        'title' => 'Logowanie i bezpieczeństwo',
    ],
    'password' => [
        'title' => 'Hasło',
        'current' => 'Obecne hasło',
        'new' => 'Nowe hasło',
        'confirmation' => 'Powtórz nowe hasło',
        'submit' => 'Zmień hasło',
        'changed' => 'Hasło zostało zmienione. Inne urządzenia zostały wylogowane.',
        'none' => 'Twoje konto nie ma jeszcze hasła. Wyślemy link, aby je ustawić.',
        'set' => 'Ustaw hasło',
        'link_sent' => 'Wysłaliśmy link na :email.',
    ],
    'sessions' => [
        'title' => 'Wyloguj się wszędzie',
        'text' => 'Kończy sesje na wszystkich innych urządzeniach i przeglądarkach. Tutaj pozostaniesz zalogowany.',
        'submit' => 'Wyloguj się wszędzie',
        'done' => 'Wylogowano cię ze wszystkich innych urządzeń.',
    ],
    'delete' => [
        'title' => 'Usuń konto',
        'text' => 'Konto i wszystko, co zawiera, zostanie usunięte na zawsze. Nie można go przywrócić.',
        'password' => 'Obecne hasło',
        'email' => 'Twój e-mail',
        'email_hint' => 'Wpisz adres konta, aby potwierdzić.',
        'wrong_email' => 'To nie jest adres tego konta.',
        'submit' => 'Usuń na zawsze',
    ],
    'social' => [
        'title' => 'Serwisy społecznościowe',
        'link' => 'Powiąż',
        'unlink' => 'Odłącz',
        'linked_short' => 'Powiązano',
        'since' => 'od :date',
        'linked' => 'Logowanie przez :provider jest powiązane.',
        'elsewhere' => 'To konto :provider jest już powiązane z innym profilem.',
        'other' => 'Powiązano już inne konto :provider. Najpierw je odłącz.',
        'last' => 'Ustaw hasło, aby odłączyć ostatni sposób logowania.',
        'not_linked' => 'Ten serwis nie jest powiązany.',
    ],
];
