<?php

declare(strict_types=1);

return [
    'title' => 'Spedizione',
    'cache' => 'Metodi di spedizione e corrieri',
    'cache_points' => 'Punti di ritiro dei corrieri',
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
    // The line of the cart's totals, and the benefit the shop's cart rules may grant (§9.4).
    'totals' => [
        'delivery' => 'Spedizione',
    ],
    'benefits' => [
        'paid_by_store' => 'Il negozio paga la spedizione',
    ],
    'hints' => [
        'no_streets' => 'La consegna a domicilio qui non è disponibile, scegli un altro metodo',
    ],
    // A street of a carrier's directory as shown: its name with the type word where the language puts it.
    'street_kinds' => [
        'street' => 'via :name',
        'avenue' => 'viale :name',
        'lane' => 'vicolo :name',
        'square' => 'piazza :name',
        'other' => ':name',
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
    'sync' => [
        'running' => 'Già in corso, pagina :page',
        'errors' => [
            'no_answer' => 'Il corriere non ha risposto',
            'key_rejected' => 'Il corriere ha rifiutato la chiave API',
            'bad_response' => 'La risposta del corriere non era nel formato previsto',
            'failed' => 'La sincronizzazione non è riuscita sul sito — vedi il log',
            'interrupted' => 'La sincronizzazione è stata interrotta',
        ],
    ],
    'schedule' => [
        'closed' => 'Chiuso',
        'around_the_clock' => 'Aperto 24 ore su 24',
        'until' => 'Fino alle :time',
        'from' => 'Apre alle :time',
    ],
];
