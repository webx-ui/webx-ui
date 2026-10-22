<?php

declare(strict_types=1);

return [
    'title' => 'Suchindex',
    'lead' => 'Der Katalog sucht, filtert und zählt mit Manticore. Antwortet Manticore nicht, antwortet das Panel aus der Datenbank.',
    'refresh' => 'Aktualisieren',
    'load-failed' => 'Der Zustand des Index ließ sich nicht laden.',

    'connection' => 'Server',
    'address' => 'Adresse',
    'prefix' => 'Tabellenpräfix',
    'version' => 'Version',
    'available' => 'Antwortet',
    'unavailable' => 'Antwortet nicht',

    'tables' => 'Tabellen',
    'language' => 'Sprache',
    'table' => 'Tabelle',
    'in-index' => 'Im Index',
    'in-database' => 'In der Datenbank',
    'state' => 'Zustand',
    'state-ready' => 'Aktuell',
    'state-stale' => 'Schema veraltet',
    'state-missing' => 'Fehlt',
    'filling' => 'Ein Neuaufbau füllt die Tabelle daneben.',
    'filled' => 'Ein Neuaufbau füllt die Tabelle daneben: :done von :total',
    'no-tables' => 'Der Server hat nicht gesagt, welche Tabellen er hat.',

    'queue' => 'Warteschlange',
    'waiting' => 'Warten aufs Schreiben',
    'oldest' => 'Die älteste seit',
    'queue-empty' => 'Jedes gespeicherte Produkt ist im Index.',

    'outdated' => 'Der Index hat nicht das Schema, das der Katalog jetzt schreibt',
    'outdated-text' => 'Der Shop antwortet solange aus den alten Tabellen, aber was ihnen fehlt — ein neuer Filter, Codes — wird nicht durchsucht. Ein Neuaufbau füllt neue Tabellen neben den aktiven und tauscht sie dann aus; bei einem großen Katalog bedeutet das Minuten an Last, wählen Sie also den Zeitpunkt.',
    'rebuild' => 'Neu aufbauen',
    'rebuild-again' => 'Erneut aufbauen',
    'rebuild-title' => 'Suchindex neu aufbauen?',
    'rebuild-text' => 'Jedes Produkt wird neu in neue Tabellen geschrieben, die dann die aktiven ersetzen. Der Shop arbeitet solange mit den alten Tabellen weiter.',
    'rebuild-busy' => 'Ein Neuaufbau läuft bereits.',
    'rebuild-locked' => 'Ein anderer Neuaufbau läuft — er wurde von der Konsole gestartet. Starten Sie diesen, sobald er endet.',
    'rebuild-console' => 'Ein von der Konsole gestarteter Neuaufbau läuft: Der Button kommt zurück, wenn er endet.',
    'rebuild-queued' => 'Wartet auf den Queue-Worker',
    'rebuild-running' => 'Wird neu aufgebaut: :done von :total',
    'rebuild-done' => 'Neu aufgebaut: :done Produkte',
    'rebuild-failed' => 'Der Neuaufbau ist fehlgeschlagen',
    'rebuild-stalled' => 'Seit einer Viertelstunde kommt vom Neuaufbau nichts: Der Queue-Worker läuft nicht oder ist stehen geblieben. Starten Sie ihn erneut, sobald der Worker läuft.',
    'rebuild-started' => 'Der Neuaufbau ist eingereiht.',
    'cancel' => 'Abbrechen',
];
