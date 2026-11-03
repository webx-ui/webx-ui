<?php

declare(strict_types=1);

return [
    'carrier' => [
        'title' => 'Nova Poshta',
    ],
    'types' => [
        'branch' => [
            'title' => 'Filiale Nova Poshta',
            'description' => 'Ritiro in una filiale del corriere; paghi la spedizione al corriere al ritiro.',
        ],
        'locker' => [
            'title' => 'Armadietto Nova Poshta',
            'description' => 'Ritiro da un armadietto del corriere; paghi la spedizione al corriere al ritiro.',
        ],
        'courier' => [
            'title' => 'Corriere Nova Poshta',
            'description' => 'Il corriere consegna il pacco alla tua porta; paghi la spedizione al corriere alla consegna.',
        ],
    ],
    'settings' => [
        'payment_method' => 'Pagamento della spedizione',
        'payment_methods' => [
            'Cash' => 'Contanti',
            'NonCash' => 'Senza contanti (con contratto con il corriere)',
        ],
        'cargo_type' => 'Tipo di carico predefinito',
        'cargo_types' => [
            'Parcel' => 'Pacco',
            'Cargo' => 'Carico',
            'Documents' => 'Documenti',
        ],
        'weight' => 'Peso predefinito, kg',
        'description' => 'Descrizione del contenuto predefinita',
        'description_default' => 'Merci',
        'sender' => 'Mittente',
        'contact' => 'Persona di contatto',
        'sender_city' => 'Città di spedizione',
        'sender_point' => 'Filiale di spedizione',
        'locker_width' => 'Locker: larghezza predefinita, cm',
        'locker_height' => 'Locker: altezza predefinita, cm',
        'locker_length' => 'Locker: lunghezza predefinita, cm',
    ],
    'credentials' => [
        'api_key' => 'Chiave API',
    ],
    'connection' => [
        'ok' => 'Mittenti nell\'account: :count',
        'key_refused' => 'Il corriere non ha accettato la chiave API.',
        'no_answer' => 'Il corriere non ha risposto.',
        'no_sender' => 'L\'account del corriere non ha alcun mittente.',
        'no_key' => 'Inserisci prima la chiave API.',
    ],
    'waybill' => [
        'weight' => 'Peso, kg',
        'seats' => 'Colli',
        'cargo_type' => 'Tipo di carico',
        'description' => 'Descrizione del carico',
        'date' => 'Data di spedizione',
        'width' => 'Larghezza, cm',
        'height' => 'Altezza, cm',
        'length' => 'Lunghezza, cm',
    ],
    'errors' => [
        'no_cod' => 'Il contrassegno non è ancora supportato da questo corriere.',
        'name_empty' => 'Compila nome e cognome del destinatario.',
        'name_letters' => 'Il corriere accetta il nome solo in lettere ucraine.',
        'phone' => 'Il corriere accetta un cellulare ucraino: +380 e nove cifre.',
        'no_sender' => 'Prima scegli nelle impostazioni del corriere il mittente, la persona di contatto, la città e la filiale di spedizione.',
        'contact_gone' => 'La persona di contatto delle impostazioni non è più nell\'account del corriere: scegline un\'altra.',
        'no_town' => 'Il corriere non conosce la località dell\'ordine: collegala nella coda delle località.',
        'no_point' => 'L\'ordine non ha una filiale o un locker del corriere.',
        'no_street' => 'L\'ordine non ha una via del corriere o un numero civico.',
        'weight' => 'Il peso deve essere maggiore di zero.',
        'weight_too_much' => 'Il peso supera quanto accetta il punto.',
        'seats' => 'I colli sono un numero intero da uno.',
        'locker_seats' => 'Un locker accetta un collo.',
        'cargo_type' => 'Scegli un tipo di carico dall\'elenco.',
        'size' => 'Le misure devono essere maggiori di zero.',
        'locker_size' => 'Un locker accetta fino a 40 × 30 × 60 cm.',
        'date' => 'La data di spedizione è oggi o un giorno entro tre mesi.',
        'read_only' => 'Questo sito usa la chiave del corriere in sola lettura: le lettere di vettura sono disattivate.',
        'no_ref' => 'L\'identificativo di questa lettera di vettura presso il corriere non è salvato: annullala nell\'account del corriere.',
        'carrier_said' => 'Il corriere ha rifiutato: :text',
    ],
    'remarks' => [
        'novaposhta_service_point' => 'Saltati, punti di servizio del corriere: :count',
        'novaposhta_deny_to_select' => 'Saltati, non selezionabili: :count',
        'novaposhta_invalid_point' => 'Saltati, con dati incompleti: :count',
    ],
];
