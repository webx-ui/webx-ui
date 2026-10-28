<?php

declare(strict_types=1);

return [
    'hello' => 'Dzień dobry, :name!',
    'ignore' => 'Jeśli to nie Ty, po prostu zignoruj tę wiadomość.',
    'verify' => [
        'subject' => 'Potwierdź e-mail w :site',
        'text' => 'Potwierdź, że to Twój adres w :site.',
        'button' => 'Potwierdź e-mail',
        'expires' => 'Link działa przez :hours godz.',
    ],
    'reset' => [
        'subject' => 'Nowe hasło w :site',
        'text' => 'Ktoś poprosił o odzyskanie hasła do Twojego konta w :site.',
        'button' => 'Ustaw nowe hasło',
        'expires' => 'Link działa przez :minutes min i tylko raz.',
    ],
    'password-changed' => [
        'subject' => 'Hasło w :site zostało zmienione',
        'text' => 'Hasło do Twojego konta w :site zostało właśnie zmienione.',
        'not-you' => 'Jeśli to nie Ty, natychmiast odzyskaj hasło.',
        'button' => 'Odzyskaj hasło',
    ],
    'email-change' => [
        'subject' => 'Potwierdź nowy adres w :site',
        'text' => 'Otwórz link, aby od teraz logować się do :site adresem :email.',
        'button' => 'Potwierdź adres',
        'expires' => 'Link działa przez :minutes min.',
    ],
    'email-changed' => [
        'subject' => 'Adres logowania w :site został zmieniony',
        'text' => 'Twoje konto w :site loguje się teraz adresem :email.',
        'not-you' => 'Jeśli to nie Ty, odzyskaj hasło i skontaktuj się z nami.',
        'button' => 'Odzyskaj hasło',
    ],
    'account-deleted' => [
        'subject' => 'Twoje konto w :site zostało usunięte',
        'text' => 'Twoje konto w :site zostało usunięte wraz z danymi.',
        'not-you' => 'Jeśli to nie ty, natychmiast się z nami skontaktuj.',
    ],
    'provider-linked' => [
        'subject' => 'Powiązano logowanie przez :provider w :site',
        'text' => 'Do Twojego konta w :site możesz teraz logować się przez :provider.',
        'not-you' => 'Jeśli to nie Ty, odłącz je na koncie i zresetuj hasło.',
        'button' => 'Logowanie i bezpieczeństwo',
    ],
];
