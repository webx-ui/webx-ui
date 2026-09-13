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
    'accepted' => 'El campo :attribute debe ser aceptado.',
    'array' => 'El campo :attribute debe ser una lista.',
    'boolean' => 'El campo :attribute debe ser sí o no.',
    'confirmed' => 'El campo :attribute no coincide con su confirmación.',
    'date' => 'El campo :attribute debe ser una fecha.',
    'different' => 'Los campos :attribute y :other deben ser distintos.',
    'email' => 'El campo :attribute debe ser un correo electrónico válido.',
    'exists' => 'No existe ese :attribute.',
    'file' => 'El campo :attribute debe ser un archivo.',
    'filled' => 'El campo :attribute no puede estar vacío.',
    'image' => 'El campo :attribute debe ser una imagen.',
    'in' => 'El campo :attribute no puede tomar ese valor.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'numeric' => 'El campo :attribute debe ser un número.',
    'present' => 'El campo :attribute debe estar presente.',
    'prohibited' => 'El campo :attribute no está permitido.',
    'required' => 'El campo :attribute es obligatorio.',
    'same' => 'Los campos :attribute y :other deben coincidir.',
    'string' => 'El campo :attribute debe ser texto.',
    'unique' => 'Ese :attribute ya está en uso.',
    'uploaded' => 'No se ha podido subir el campo :attribute.',
    'url' => 'El campo :attribute debe ser una dirección válida.',
    'uuid' => 'El campo :attribute debe ser un UUID válido.',
    'mimes' => 'El campo :attribute debe ser un archivo de tipo: :values.',
    'max' => [
        'numeric' => 'El campo :attribute no puede ser mayor que :max.',
        'file' => 'El campo :attribute no puede pesar más de :max kilobytes.',
        'string' => 'El campo :attribute no puede tener más de :max caracteres.',
        'array' => 'El campo :attribute no puede tener más de :max elementos.',
    ],
    'min' => [
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'file' => 'El campo :attribute debe pesar al menos :min kilobytes.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
        'array' => 'El campo :attribute debe tener al menos :min elementos.',
    ],
    'between' => [
        'numeric' => 'El campo :attribute debe estar entre :min y :max.',
        'file' => 'El campo :attribute debe pesar entre :min y :max kilobytes.',
        'string' => 'El campo :attribute debe tener entre :min y :max caracteres.',
        'array' => 'El campo :attribute debe tener entre :min y :max elementos.',
    ],
    'size' => [
        'numeric' => 'El campo :attribute debe ser :size.',
        'file' => 'El campo :attribute debe pesar :size kilobytes.',
        'string' => 'El campo :attribute debe tener :size caracteres.',
        'array' => 'El campo :attribute debe contener :size elementos.',
    ],
    'gt' => [
        'numeric' => 'El campo :attribute debe ser mayor que :value.',
        'file' => 'El campo :attribute debe pesar más de :value kilobytes.',
        'string' => 'El campo :attribute debe tener más de :value caracteres.',
        'array' => 'El campo :attribute debe tener más de :value elementos.',
    ],
    'lt' => [
        'numeric' => 'El campo :attribute debe ser menor que :value.',
        'file' => 'El campo :attribute debe pesar menos de :value kilobytes.',
        'string' => 'El campo :attribute debe tener menos de :value caracteres.',
        'array' => 'El campo :attribute debe tener menos de :value elementos.',
    ],
];
