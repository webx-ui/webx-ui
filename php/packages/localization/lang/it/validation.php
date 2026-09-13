<?php

declare(strict_types=1);

/*
 * Laravel ships these in English only.
 *
 * What is here is the rules the WebX packages validate with, plus the handful any form runs
 * into — not the whole of Laravel's file. A line nobody can reach is a line nobody checks, and
 * an application that needs more publishes its own: its `lang/` is read first.
 */

return [
    'accepted' => 'Il campo :attribute deve essere accettato.',
    'array' => 'Il campo :attribute deve essere un elenco.',
    'boolean' => 'Il campo :attribute deve essere sì o no.',
    'confirmed' => 'Il campo :attribute non corrisponde alla conferma.',
    'date' => 'Il campo :attribute deve essere una data.',
    'different' => 'I campi :attribute e :other devono essere diversi.',
    'email' => 'Il campo :attribute deve essere un indirizzo email valido.',
    'exists' => 'Questo :attribute non esiste.',
    'file' => 'Il campo :attribute deve essere un file.',
    'filled' => 'Il campo :attribute non può essere vuoto.',
    'image' => 'Il campo :attribute deve essere un’immagine.',
    'in' => 'Il campo :attribute non può avere questo valore.',
    'integer' => 'Il campo :attribute deve essere un numero intero.',
    'numeric' => 'Il campo :attribute deve essere un numero.',
    'present' => 'Il campo :attribute deve essere presente.',
    'prohibited' => 'Il campo :attribute non è consentito.',
    'required' => 'Il campo :attribute è obbligatorio.',
    'same' => 'I campi :attribute e :other devono coincidere.',
    'string' => 'Il campo :attribute deve essere testo.',
    'unique' => 'Questo :attribute è già in uso.',
    'uploaded' => 'Non è stato possibile caricare il campo :attribute.',
    'url' => 'Il campo :attribute deve essere un indirizzo valido.',
    'uuid' => 'Il campo :attribute deve essere un UUID valido.',
    'mimes' => 'Il campo :attribute deve essere un file di tipo: :values.',
    'max' => [
        'numeric' => 'Il campo :attribute non può essere maggiore di :max.',
        'file' => 'Il campo :attribute non può superare :max kilobyte.',
        'string' => 'Il campo :attribute non può superare :max caratteri.',
        'array' => 'Il campo :attribute non può avere più di :max elementi.',
    ],
    'min' => [
        'numeric' => 'Il campo :attribute deve essere almeno :min.',
        'file' => 'Il campo :attribute deve essere almeno :min kilobyte.',
        'string' => 'Il campo :attribute deve avere almeno :min caratteri.',
        'array' => 'Il campo :attribute deve avere almeno :min elementi.',
    ],
    'between' => [
        'numeric' => 'Il campo :attribute deve essere tra :min e :max.',
        'file' => 'Il campo :attribute deve essere tra :min e :max kilobyte.',
        'string' => 'Il campo :attribute deve avere tra :min e :max caratteri.',
        'array' => 'Il campo :attribute deve avere tra :min e :max elementi.',
    ],
    'size' => [
        'numeric' => 'Il campo :attribute deve essere :size.',
        'file' => 'Il campo :attribute deve essere di :size kilobyte.',
        'string' => 'Il campo :attribute deve avere :size caratteri.',
        'array' => 'Il campo :attribute deve contenere :size elementi.',
    ],
    'gt' => [
        'numeric' => 'Il campo :attribute deve essere maggiore di :value.',
        'file' => 'Il campo :attribute deve superare :value kilobyte.',
        'string' => 'Il campo :attribute deve superare :value caratteri.',
        'array' => 'Il campo :attribute deve avere più di :value elementi.',
    ],
    'lt' => [
        'numeric' => 'Il campo :attribute deve essere minore di :value.',
        'file' => 'Il campo :attribute deve essere sotto :value kilobyte.',
        'string' => 'Il campo :attribute deve avere meno di :value caratteri.',
        'array' => 'Il campo :attribute deve avere meno di :value elementi.',
    ],
];
