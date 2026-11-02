<?php

declare(strict_types=1);

return [
    'title' => 'Versand',
    'cache' => 'Versandarten und Versanddienste',
    'cache_points' => 'Abholstellen der Versanddienste',
    'carriers' => [
        'store' => 'Der Shop',
    ],
    'point_kinds' => [
        'branch' => 'Filiale',
        'locker' => 'Paketstation',
        'store' => 'Geschäft',
        'pickup' => 'Abholstelle',
    ],
    'locality_kinds' => [
        'city' => 'Stadt',
        'town' => 'Stadt',
        'township' => 'Siedlung städtischen Typs',
        'village' => 'Dorf',
        'settlement' => 'Siedlung',
        'other' => 'Ort',
    ],
    'step' => [
        'delivery' => 'Versand',
        'locality' => 'Stadt oder Ort',
        'locality_placeholder' => 'Namen eingeben',
        'where' => 'Wohin soll die Bestellung geliefert werden?',
        'choose_locality' => 'Stadt auswählen',
        'change' => 'Ändern',
        'popular' => 'Beliebte Städte',
        'show_methods' => 'Versandarten anzeigen',
        'no_methods' => 'Hierher gibt es keine Versandarten',
        'free' => 'Kostenlos',
        'carrier_rates' => 'Nach Tarif des Versanddienstes',
        'choose_point' => 'Filiale auswählen',
        'pick_up_here' => 'Hier abholen',
        'open_until' => 'Heute geöffnet bis :time',
        'closed_today' => 'Heute geschlossen',
        'street' => 'Straße',
        'house' => 'Hausnummer',
        'apartment' => 'Wohnung',
        'note' => 'Hinweis für den Kurier',
        'cost' => 'Versandkosten',
        'points' => 'Stellen: :count',
    ],
    'hints' => [
        'no_streets' => 'Kurierzustellung ist hier nicht möglich, wählen Sie eine andere Versandart',
    ],
    'errors' => [
        'choose_point' => 'Wählen Sie eine Abholstelle.',
        'point_gone' => 'Die Abholstelle ist nicht mehr verfügbar, wählen Sie eine andere.',
        'choose_street' => 'Wählen Sie die Straße aus der Liste.',
        'street_required' => 'Geben Sie die Straße an.',
        'house_required' => 'Geben Sie die Hausnummer an.',
        'too_long' => 'Höchstens :max Zeichen.',
        'no_quote' => 'Der Versand konnte nicht berechnet werden, wählen Sie eine andere Versandart.',
    ],
    'sync' => [
        'running' => 'Läuft bereits, Seite :page',
        'errors' => [
            'no_answer' => 'Der Versanddienst hat nicht geantwortet',
            'key_rejected' => 'Der Versanddienst hat den API-Schlüssel abgelehnt',
            'bad_response' => 'Die Antwort des Versanddienstes hatte nicht das erwartete Format',
            'failed' => 'Die Synchronisierung ist auf Seiten der Website fehlgeschlagen — siehe Log',
            'interrupted' => 'Die Synchronisierung wurde unterbrochen',
        ],
    ],
    'schedule' => [
        'closed' => 'Geschlossen',
        'around_the_clock' => 'Rund um die Uhr geöffnet',
        'until' => 'Bis :time',
        'from' => 'Öffnet um :time',
    ],
];
