<?php

declare(strict_types=1);

return [
    'hello' => 'Hallo, :name!',
    'ignore' => 'Wenn Sie das nicht angefordert haben, ignorieren Sie diese E-Mail einfach.',
    'verify' => [
        'subject' => 'Bestätigen Sie Ihre E-Mail auf :site',
        'text' => 'Bitte bestätigen Sie, dass dies Ihre Adresse auf :site ist.',
        'button' => 'E-Mail bestätigen',
        'expires' => 'Der Link gilt :hours Stunden.',
    ],
    'reset' => [
        'subject' => 'Ein neues Passwort auf :site',
        'text' => 'Jemand hat die Wiederherstellung des Passworts Ihres Kontos auf :site angefordert.',
        'button' => 'Neues Passwort festlegen',
        'expires' => 'Der Link gilt :minutes Minuten und nur einmal.',
    ],
    'password-changed' => [
        'subject' => 'Ihr Passwort auf :site wurde geändert',
        'text' => 'Das Passwort Ihres Kontos auf :site wurde soeben geändert.',
        'not-you' => 'Wenn Sie das nicht waren, stellen Sie das Passwort sofort wieder her.',
        'button' => 'Passwort wiederherstellen',
    ],
    'email-change' => [
        'subject' => 'Bestätigen Sie die neue Adresse auf :site',
        'text' => 'Folgen Sie dem Link, um sich ab jetzt mit :email auf :site anzumelden.',
        'button' => 'Adresse bestätigen',
        'expires' => 'Der Link gilt :minutes Minuten.',
    ],
    'email-changed' => [
        'subject' => 'Ihre Anmeldeadresse auf :site wurde geändert',
        'text' => 'Ihr Konto auf :site meldet sich jetzt mit :email an.',
        'not-you' => 'Wenn Sie das nicht waren, stellen Sie das Passwort wieder her und kontaktieren Sie uns.',
        'button' => 'Passwort wiederherstellen',
    ],
    'account-deleted' => [
        'subject' => 'Ihr Konto bei :site wurde gelöscht',
        'text' => 'Ihr Konto bei :site wurde samt seinen Daten gelöscht.',
        'not-you' => 'Wenn Sie das nicht waren, kontaktieren Sie uns sofort.',
    ],
    'provider-linked' => [
        'subject' => 'Anmeldung mit :provider auf :site verknüpft',
        'text' => 'Bei Ihrem Konto auf :site können Sie sich jetzt mit :provider anmelden.',
        'not-you' => 'Wenn Sie das nicht waren, trennen Sie die Verknüpfung im Konto und setzen Sie das Passwort zurück.',
        'button' => 'Anmeldung und Sicherheit',
    ],
];
