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
];
