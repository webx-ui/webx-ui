<?php

declare(strict_types=1);

return [
    'sign_in' => [
        'title' => 'Logowanie',
        'remember' => 'Zapamiętaj mnie',
        'submit' => 'Zaloguj się',
        'no_account' => 'Nie masz jeszcze konta?',
        'register' => 'Zarejestruj się',
    ],
    'register' => [
        'title' => 'Załóż konto',
        'submit' => 'Zarejestruj się',
        'password_hint' => 'Co najmniej :min znaków.',
        'have_account' => 'Masz już konto?',
        'sign_in' => 'Zaloguj się',
    ],
    'fields' => [
        'email' => 'E-mail',
        'password' => 'Hasło',
        'first_name' => 'Imię',
        'last_name' => 'Nazwisko',
        'phone' => 'Telefon',
    ],
    'failed' => 'Nieprawidłowy e-mail lub hasło.',
    'blocked' => 'To konto jest zablokowane. Skontaktuj się ze sklepem.',
    'taken' => 'Ten adres jest już zarejestrowany — zaloguj się lub odzyskaj hasło.',
    'throttled' => 'Zbyt wiele prób. Odczekaj :seconds s.',
    'forgot' => [
        'link' => 'Nie pamiętasz hasła?',
        'title' => 'Odzyskiwanie hasła',
        'intro' => 'Podaj e-mail konta — wyślemy link do ustawienia nowego hasła.',
        'submit' => 'Wyślij link',
        'sent' => 'Jeśli istnieje konto z tym adresem, wysłaliśmy na niego link.',
        'back' => 'Wróć do logowania',
    ],
    'reset' => [
        'title' => 'Nowe hasło',
        'password' => 'Nowe hasło',
        'confirmation' => 'Powtórz hasło',
        'submit' => 'Zapisz hasło',
        'expired' => 'Link wygasł. Poproś o nowy poniżej.',
    ],
    'verify' => [
        'banner' => 'Potwierdź swój e-mail: wysłaliśmy wiadomość na',
        'resend' => 'Wyślij ponownie',
        'sent_short' => 'Wysłano',
        'close' => 'Zamknij',
        'sent' => 'Ponownie wysłaliśmy wiadomość na :email.',
        'already' => 'Twój e-mail jest już potwierdzony.',
        'throttled' => 'Wiadomość została już wysłana. Spróbuj ponownie za :minutes min.',
        'sign_in' => 'Zaloguj się',
        'continue' => 'Dalej',
        'confirmed' => [
            'title' => 'E-mail potwierdzony',
            'text' => 'Dziękujemy — adres jest potwierdzony.',
        ],
        'expired' => [
            'title' => 'Link wygasł',
            'text' => 'Ten link już nie działa. Zaloguj się, aby otrzymać nową wiadomość.',
        ],
        'unverified' => [
            'title' => 'Potwierdź swój e-mail',
            'text' => 'Ta strona otworzy się po potwierdzeniu e-maila. Wysłaliśmy wiadomość na :email.',
        ],
    ],
    'email' => [
        'new' => 'Nowy e-mail',
        'password' => 'Obecne hasło',
        'wrong_password' => 'Hasło jest nieprawidłowe.',
        'same' => 'Już logujesz się tym adresem.',
        'taken' => 'Ten adres należy do innego konta.',
        'sent' => 'Wysłaliśmy link na :email. Adres zmieni się, gdy go otworzysz.',
        'changed' => [
            'title' => 'Adres zmieniony',
            'text' => 'Teraz logujesz się adresem :email.',
        ],
    ],
];
