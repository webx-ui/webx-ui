<?php

declare(strict_types=1);

return [
    'title' => 'Spedizione',
    'cache' => 'Metodi di spedizione e corrieri',
    'carriers' => [
        'store' => 'Il negozio',
    ],
    'point_kinds' => [
        'branch' => 'Filiale',
        'locker' => 'Locker',
        'store' => 'Negozio',
        'pickup' => 'Punto di ritiro',
    ],
    'locality_kinds' => [
        'city' => 'Città',
        'town' => 'Città',
        'township' => 'Borgo',
        'village' => 'Paese',
        'settlement' => 'Frazione',
        'other' => 'Località',
    ],
    'step' => [
        'delivery' => 'Spedizione',
        'locality' => 'Città o località',
        'locality_placeholder' => 'Inizia a scrivere il nome',
        'where' => 'Dove consegnare l’ordine?',
        'choose_locality' => 'Scegli una città',
        'change' => 'Cambia',
        'popular' => 'Città popolari',
        'show_methods' => 'Mostra i metodi di spedizione',
        'no_methods' => 'Qui non ci sono metodi di spedizione',
        'free' => 'Gratis',
        'carrier_rates' => 'Secondo le tariffe del corriere',
        'choose_point' => 'Scegli una filiale',
        'pick_up_here' => 'Ritiro qui',
        'open_until' => 'Oggi aperto fino alle :time',
        'closed_today' => 'Oggi chiuso',
        'street' => 'Via',
        'house' => 'Numero civico',
        'apartment' => 'Interno',
        'note' => 'Nota per il corriere',
        'cost' => 'Costo di spedizione',
        'points' => 'Punti: :count',
    ],
    'hints' => [
        'no_streets' => 'La consegna a domicilio qui non è disponibile, scegli un altro metodo',
    ],
    'errors' => [
        'choose_point' => 'Scegli un punto di ritiro.',
        'point_gone' => 'Il punto di ritiro non è più disponibile, scegline un altro.',
        'choose_street' => 'Scegli la via dall’elenco.',
        'street_required' => 'Indica la via.',
        'house_required' => 'Indica il numero civico.',
        'too_long' => 'Al massimo :max caratteri.',
        'no_quote' => 'Impossibile calcolare la spedizione, scegli un altro metodo.',
    ],
];
