<?php

declare(strict_types=1);

return [
    'sign_in' => [
        'title' => 'Anmelden',
        'remember' => 'Angemeldet bleiben',
        'submit' => 'Anmelden',
        'no_account' => 'Noch kein Konto?',
        'register' => 'Registrieren',
    ],
    'register' => [
        'title' => 'Konto erstellen',
        'submit' => 'Registrieren',
        'password_hint' => 'Mindestens :min Zeichen.',
        'have_account' => 'Schon ein Konto?',
        'sign_in' => 'Anmelden',
    ],
    'fields' => [
        'email' => 'E-Mail',
        'password' => 'Passwort',
        'first_name' => 'Vorname',
        'last_name' => 'Nachname',
        'phone' => 'Telefon',
    ],
    'failed' => 'E-Mail oder Passwort ist falsch.',
    'blocked' => 'Dieses Konto ist gesperrt. Bitte wenden Sie sich an den Shop.',
    'taken' => 'Diese Adresse ist bereits registriert — melden Sie sich an oder setzen Sie Ihr Passwort zurück.',
    'value_taken' => 'Dieser Wert ist bereits vergeben.',
    'throttled' => 'Zu viele Versuche. Bitte warten Sie :seconds s.',
    'forgot' => [
        'link' => 'Passwort vergessen?',
        'title' => 'Passwort wiederherstellen',
        'intro' => 'Geben Sie die E-Mail Ihres Kontos ein — wir senden einen Link für ein neues Passwort.',
        'submit' => 'Link senden',
        'sent' => 'Wenn es ein Konto mit dieser Adresse gibt, haben wir einen Link dorthin gesendet.',
        'back' => 'Zurück zur Anmeldung',
    ],
    'reset' => [
        'title' => 'Neues Passwort',
        'password' => 'Neues Passwort',
        'confirmation' => 'Passwort wiederholen',
        'submit' => 'Passwort speichern',
        'expired' => 'Der Link ist abgelaufen. Fordern Sie unten einen neuen an.',
    ],
    'verify' => [
        'banner' => 'Bitte bestätigen Sie Ihre E-Mail: Wir haben einen Brief gesendet an',
        'resend' => 'Erneut senden',
        'sent_short' => 'Gesendet',
        'close' => 'Schließen',
        'sent' => 'Wir haben die E-Mail erneut an :email gesendet.',
        'already' => 'Ihre E-Mail ist bereits bestätigt.',
        'throttled' => 'Die E-Mail wurde bereits gesendet. Versuchen Sie es in :minutes Min. erneut.',
        'sign_in' => 'Anmelden',
        'continue' => 'Weiter',
        'confirmed' => [
            'title' => 'E-Mail bestätigt',
            'text' => 'Danke — Ihre Adresse ist bestätigt.',
        ],
        'expired' => [
            'title' => 'Der Link ist abgelaufen',
            'text' => 'Dieser Link funktioniert nicht mehr. Melden Sie sich an, um eine neue E-Mail zu erhalten.',
        ],
        'unverified' => [
            'title' => 'Bestätigen Sie Ihre E-Mail',
            'text' => 'Diese Seite öffnet sich, nachdem Sie Ihre E-Mail bestätigt haben. Wir haben eine E-Mail an :email gesendet.',
        ],
    ],
    'email' => [
        'new' => 'Neue E-Mail',
        'password' => 'Aktuelles Passwort',
        'wrong_password' => 'Das Passwort ist falsch.',
        'same' => 'Mit dieser Adresse melden Sie sich bereits an.',
        'taken' => 'Diese Adresse gehört zu einem anderen Konto.',
        'sent' => 'Wir haben einen Link an :email gesendet. Die Adresse ändert sich, sobald Sie ihm folgen.',
        'changed' => [
            'title' => 'Adresse geändert',
            'text' => 'Sie melden sich jetzt mit :email an.',
        ],
    ],
];
