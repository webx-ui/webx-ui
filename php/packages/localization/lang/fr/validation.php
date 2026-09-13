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
    'accepted' => 'Le champ :attribute doit être accepté.',
    'array' => 'Le champ :attribute doit être une liste.',
    'boolean' => 'Le champ :attribute doit être oui ou non.',
    'confirmed' => 'Le champ :attribute ne correspond pas à sa confirmation.',
    'date' => 'Le champ :attribute doit être une date.',
    'different' => 'Les champs :attribute et :other doivent être différents.',
    'email' => 'Le champ :attribute doit être une adresse e-mail valide.',
    'exists' => 'Ce :attribute n’existe pas.',
    'file' => 'Le champ :attribute doit être un fichier.',
    'filled' => 'Le champ :attribute ne peut pas être vide.',
    'image' => 'Le champ :attribute doit être une image.',
    'in' => 'Le champ :attribute ne peut pas prendre cette valeur.',
    'integer' => 'Le champ :attribute doit être un nombre entier.',
    'numeric' => 'Le champ :attribute doit être un nombre.',
    'present' => 'Le champ :attribute doit être présent.',
    'prohibited' => 'Le champ :attribute n’est pas autorisé.',
    'required' => 'Le champ :attribute est obligatoire.',
    'same' => 'Les champs :attribute et :other doivent correspondre.',
    'string' => 'Le champ :attribute doit être du texte.',
    'unique' => 'Ce :attribute est déjà pris.',
    'uploaded' => 'Le champ :attribute n’a pas pu être envoyé.',
    'url' => 'Le champ :attribute doit être une adresse valide.',
    'uuid' => 'Le champ :attribute doit être un UUID valide.',
    'mimes' => 'Le champ :attribute doit être un fichier de type : :values.',
    'max' => [
        'numeric' => 'Le champ :attribute ne doit pas dépasser :max.',
        'file' => 'Le champ :attribute ne doit pas dépasser :max kilooctets.',
        'string' => 'Le champ :attribute ne doit pas dépasser :max caractères.',
        'array' => 'Le champ :attribute ne doit pas contenir plus de :max éléments.',
    ],
    'min' => [
        'numeric' => 'Le champ :attribute doit être au moins :min.',
        'file' => 'Le champ :attribute doit faire au moins :min kilooctets.',
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
        'array' => 'Le champ :attribute doit contenir au moins :min éléments.',
    ],
    'between' => [
        'numeric' => 'Le champ :attribute doit être entre :min et :max.',
        'file' => 'Le champ :attribute doit faire entre :min et :max kilooctets.',
        'string' => 'Le champ :attribute doit contenir entre :min et :max caractères.',
        'array' => 'Le champ :attribute doit contenir entre :min et :max éléments.',
    ],
    'size' => [
        'numeric' => 'Le champ :attribute doit être :size.',
        'file' => 'Le champ :attribute doit faire :size kilooctets.',
        'string' => 'Le champ :attribute doit contenir :size caractères.',
        'array' => 'Le champ :attribute doit contenir :size éléments.',
    ],
    'gt' => [
        'numeric' => 'Le champ :attribute doit être supérieur à :value.',
        'file' => 'Le champ :attribute doit faire plus de :value kilooctets.',
        'string' => 'Le champ :attribute doit contenir plus de :value caractères.',
        'array' => 'Le champ :attribute doit contenir plus de :value éléments.',
    ],
    'lt' => [
        'numeric' => 'Le champ :attribute doit être inférieur à :value.',
        'file' => 'Le champ :attribute doit faire moins de :value kilooctets.',
        'string' => 'Le champ :attribute doit contenir moins de :value caractères.',
        'array' => 'Le champ :attribute doit contenir moins de :value éléments.',
    ],
];
