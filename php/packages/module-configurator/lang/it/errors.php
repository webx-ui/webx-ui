<?php

declare(strict_types=1);

return [
    'schema' => [
        'invalid' => 'Lo schema non è un oggetto JSON.',
        'version' => 'Versione dello schema sconosciuta: questo modulo legge la versione 1.',
    ],
    'currency' => [
        'invalid' => 'La valuta è un codice ISO 4217 di tre lettere maiuscole, come UAH o EUR.',
    ],
    'pricing' => [
        'round' => 'Il passo di arrotondamento è un numero maggiore di 0.',
    ],
    'discount' => [
        'percent' => 'Lo sconto è una percentuale maggiore di 0 e minore di 100.',
        'until' => 'L’ultimo giorno dello sconto è una data AAAA-MM-GG.',
    ],
    'steps' => [
        'empty' => 'Un configuratore ha bisogno di almeno un passaggio.',
    ],
    'key' => [
        'invalid' => 'La chiave «:key» non è fatta di a-z, 0-9 e _ con una lettera all’inizio.',
        'duplicate' => 'La chiave «:key» è già usata: passaggi e campi delle misure condividono le chiavi.',
    ],
    'type' => [
        'unknown' => 'Tipo di passaggio sconosciuto: cards, swatches, buttons o dimensions.',
    ],
    'options' => [
        'empty' => 'Un passaggio di scelta ha bisogno di almeno un’opzione.',
    ],
    'fields' => [
        'empty' => 'Un passaggio delle misure ha bisogno di almeno un campo.',
    ],
    'value' => [
        'invalid' => 'Il valore «:value» non è uno slug: a-z, 0-9, - e _.',
        'duplicate' => 'Il valore «:value» compare due volte in questo passaggio.',
    ],
    'label' => [
        'missing' => 'Serve un nome nella lingua predefinita (:locale).',
    ],
    'text' => [
        'invalid' => 'Un testo è una stringa o un insieme di lingue.',
    ],
    'image' => [
        'missing' => 'Scegli un’immagine dalla mediateca.',
        'unknown' => 'La mediateca non ha il file «:key».',
    ],
    'product' => [
        'invalid' => 'Un prodotto si indica con il suo codice articolo (SKU), fino a 64 caratteri.',
        'unknown' => 'Il catalogo non ha prodotti con il codice «:sku».',
    ],
    'swatch' => [
        'invalid' => 'Un campione è #rgb, #rrggbb o una sfumatura di colori hex.',
    ],
    'price' => [
        'invalid' => 'Un prezzo è un numero.',
        'per_m2' => 'Un prezzo al m² richiede una superficie: dai alle opzioni di un passaggio una formula della superficie.',
    ],
    'area' => [
        'main_count' => 'Solo le opzioni di un passaggio possono avere una formula della superficie.',
        'missing' => 'Ogni opzione del passaggio principale ha bisogno di una formula della superficie.',
        'syntax' => 'La formula è sbagliata al carattere :position: :reason.',
        'unknown_key' => 'La formula usa «:key», che non è un campo delle misure.',
    ],
    'range' => [
        'invalid' => 'L’intervallo è sbagliato: min sopra max o un passo non maggiore di 0.',
    ],
    'limits' => [
        'unknown_field' => 'limits nomina «:key», che non è un campo delle misure.',
    ],
    'defaults' => [
        'unknown_field' => 'defaults nomina «:key», che non è un campo delle misure.',
    ],
    'condition' => [
        'invalid' => 'Una condizione è un passaggio e i valori con cui è scelto.',
        'unknown_step' => 'Non esiste il passaggio «:step».',
        'forward' => 'Il passaggio «:step» non sta sopra questo: una condizione guarda solo in alto.',
        'not_choice' => 'Il passaggio «:step» è delle misure: una condizione nomina un passaggio con opzioni.',
        'unknown_value' => 'Il passaggio «:step» non ha l’opzione «:value».',
    ],
    'initial' => [
        'unknown' => 'La scelta iniziale «:key» non è un passaggio, un campo o uno dei suoi valori.',
    ],
    'previews' => [
        'condition' => 'Una regola d’immagine nomina un passaggio o un valore che non esiste.',
    ],
    'values' => [
        'range' => 'La misura «:key» va da :min a :max.',
        'number' => 'La misura «:key» è un numero.',
    ],
];
