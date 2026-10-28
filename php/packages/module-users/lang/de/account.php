<?php

declare(strict_types=1);

return [
    'menu' => [
        'label' => 'Kundenkonto',
        'profile' => 'Profil',
        'security' => 'Anmeldung und Sicherheit',
        'sign_out' => 'Abmelden',
    ],
    'profile' => [
        'title' => 'Profil',
        'personal' => 'Persönliche Daten',
        'save' => 'Speichern',
        'saved' => 'Gespeichert.',
    ],
    'email' => [
        'title' => 'E-Mail',
        'confirmed' => 'bestätigt',
        'unconfirmed' => 'nicht bestätigt',
        'pending' => 'Wartet auf Bestätigung: :email — folgen Sie dem Link in der E-Mail.',
        'submit' => 'E-Mail ändern',
    ],
    'security' => [
        'title' => 'Anmeldung und Sicherheit',
    ],
    'password' => [
        'title' => 'Passwort',
        'current' => 'Aktuelles Passwort',
        'new' => 'Neues Passwort',
        'confirmation' => 'Neues Passwort wiederholen',
        'submit' => 'Passwort ändern',
        'changed' => 'Das Passwort wurde geändert. Andere Geräte wurden abgemeldet.',
        'none' => 'Ihr Konto hat noch kein Passwort. Wir senden Ihnen einen Link, um eines festzulegen.',
        'set' => 'Passwort festlegen',
        'link_sent' => 'Wir haben einen Link an :email gesendet.',
    ],
    'sessions' => [
        'title' => 'Überall abmelden',
        'text' => 'Beendet die Sitzungen auf allen anderen Geräten und Browsern. Hier bleiben Sie angemeldet.',
        'submit' => 'Überall abmelden',
        'done' => 'Sie wurden auf allen anderen Geräten abgemeldet.',
    ],
    'delete' => [
        'title' => 'Konto löschen',
        'text' => 'Das Konto und alles, was darin gespeichert ist, wird endgültig gelöscht. Es kann nicht wiederhergestellt werden.',
        'password' => 'Aktuelles Passwort',
        'email' => 'Ihre E-Mail',
        'email_hint' => 'Geben Sie zur Bestätigung die Adresse des Kontos ein.',
        'wrong_email' => 'Das ist nicht die Adresse dieses Kontos.',
        'submit' => 'Endgültig löschen',
    ],
    'social' => [
        'title' => 'Soziale Netzwerke',
        'link' => 'Verknüpfen',
        'unlink' => 'Trennen',
        'linked_short' => 'Verknüpft',
        'since' => 'seit :date',
        'linked' => 'Die Anmeldung mit :provider ist verknüpft.',
        'elsewhere' => 'Dieses :provider-Konto ist bereits mit einem anderen Profil verknüpft.',
        'other' => 'Ein anderes :provider-Konto ist bereits verknüpft. Trennen Sie es zuerst.',
        'last' => 'Legen Sie ein Passwort fest, um die letzte Anmeldemöglichkeit zu trennen.',
        'not_linked' => 'Dieses Netzwerk ist nicht verknüpft.',
    ],
];
