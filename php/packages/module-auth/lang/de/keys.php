<?php

declare(strict_types=1);

/*
 * Keys for programs: issued under Administrators → Keys, shown once, revoked there. The words
 * are about a key a program holds, not about tokens and scopes underneath — except the scopes
 * themselves, which are kept as issued because an agent's refusal quotes them.
 */

return [
    'title' => 'Schlüssel',
    'issue' => 'Schlüssel ausstellen',
    'issue-short' => 'Ausstellen',
    'lead' => 'Ein Schlüssel lässt ein Programm — einen Warteschlangen-Wächter, einen nächtlichen Import — ohne Browser in diesem Panel arbeiten. Er handelt als der Administrator, den Sie wählen, und darf nie mehr als dieser. Ein Schlüssel ist ein Programm, keine Person: Was ein Modul einer Person vorbehält, etwa eine von Hand geprüfte Stufe, kann er nicht.',
    'name' => 'Name',
    'name-hint' => 'Was das Programm ist, damit die Liste es sagt: „Warteschlangen-Wächter“, „nächtlicher Import“.',
    'who' => 'Handelt als',
    'who-hint' => 'Alles, was der Schlüssel ändert, geschieht im Namen dieses Administrators und im Rahmen seiner Rechte.',
    'allowed' => 'Was erlaubt ist',
    'everything' => 'Alles, was der Administrator darf',
    'chosen' => 'Nur die unten gewählten Scopes',
    'scopes' => 'Scopes',
    'scopes-hint' => 'Ein später installiertes Modul bleibt unerreichbar, bis ein neuer Schlüssel es nennt.',
    'scopes-required' => 'Wählen Sie mindestens einen Scope.',
    'lifetime' => 'Gültig für',
    'lifetime-hint' => 'Ein Schlüssel lässt sich nicht verlängern: Stellen Sie einen neuen aus, bevor dieser abläuft, und widerrufen Sie den alten.',
    'days-30' => '30 Tage',
    'days-90' => '90 Tage',
    'days-365' => '1 Jahr',
    'days-1095' => '3 Jahre',
    'days-3650' => '10 Jahre',
    'issued-title' => 'Kopieren Sie den Schlüssel jetzt',
    'issued-text' => 'Der Schlüssel wird nur dieses eine Mal angezeigt. Niemand kann ihn wieder sehen — weder hier noch auf dem Server. Geht er verloren, widerrufen Sie ihn und stellen einen neuen aus.',
    'secret' => 'Schlüssel',
    'copy' => 'Kopieren',
    'copied' => 'Kopiert',
    'copy-failed' => 'Kein Zugriff auf die Zwischenablage. Markieren Sie den Schlüssel und kopieren Sie ihn von Hand.',
    'address' => 'Serveradresse',
    'header-hint' => 'Das Programm sendet den Schlüssel in einem Header: Authorization: Bearer <Schlüssel>.',
    'done' => 'Fertig',
    'cancel' => 'Abbrechen',
    'expires' => 'Läuft ab',
    'until' => 'bis',
    'last-used' => 'Zuletzt benutzt',
    'never-used' => 'Nie',
    'issued-by' => 'Ausgestellt von :name',
    'issued-console' => 'Im Terminal ausgestellt',
    'full' => 'Alles, was :name darf',
    'deleted' => 'Administrator Nr. :id',
    'revoke' => 'Widerrufen',
    'revoked' => 'Widerrufen',
    'expired' => 'Abgelaufen',
    'confirm-title' => '„:name“ widerrufen?',
    'confirm-text' => 'Das Programm, das ihn nutzt, wird beim nächsten Aufruf abgewiesen. Ein widerrufener Schlüssel lässt sich nicht wieder einschalten.',
    'confirm' => 'Widerrufen',
    'revoked-done' => '„:name“ ist widerrufen',
    'empty' => 'Es wurden noch keine Schlüssel ausgestellt',
];
