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
    'accepted' => 'The :attribute field must be accepted.',
    'array' => 'The :attribute field must be a list.',
    'boolean' => 'The :attribute field must be true or false.',
    'confirmed' => 'The :attribute field does not match its confirmation.',
    'date' => 'The :attribute field must be a date.',
    'different' => 'The :attribute field and :other must be different.',
    'email' => 'The :attribute field must be a valid email address.',
    'exists' => 'There is no such :attribute.',
    'file' => 'The :attribute field must be a file.',
    'filled' => 'The :attribute field must have a value.',
    'image' => 'The :attribute field must be an image.',
    'in' => 'That is not one of the values :attribute may take.',
    'integer' => 'The :attribute field must be a whole number.',
    'numeric' => 'The :attribute field must be a number.',
    'present' => 'The :attribute field must be present.',
    'prohibited' => 'The :attribute field is not allowed.',
    'required' => 'The :attribute field is required.',
    'same' => 'The :attribute field and :other must match.',
    'string' => 'The :attribute field must be text.',
    'unique' => 'That :attribute is already taken.',
    'uploaded' => 'The :attribute field failed to upload.',
    'url' => 'The :attribute field must be a valid address.',
    'uuid' => 'The :attribute field must be a valid UUID.',
    'mimes' => 'The :attribute field must be a file of type: :values.',
    'max' => [
        'numeric' => 'The :attribute field must not be greater than :max.',
        'file' => 'The :attribute field must not be greater than :max kilobytes.',
        'string' => 'The :attribute field must not be greater than :max characters.',
        'array' => 'The :attribute field must not have more than :max items.',
    ],
    'min' => [
        'numeric' => 'The :attribute field must be at least :min.',
        'file' => 'The :attribute field must be at least :min kilobytes.',
        'string' => 'The :attribute field must be at least :min characters.',
        'array' => 'The :attribute field must have at least :min items.',
    ],
    'between' => [
        'numeric' => 'The :attribute field must be between :min and :max.',
        'file' => 'The :attribute field must be between :min and :max kilobytes.',
        'string' => 'The :attribute field must be between :min and :max characters.',
        'array' => 'The :attribute field must have between :min and :max items.',
    ],
    'size' => [
        'numeric' => 'The :attribute field must be :size.',
        'file' => 'The :attribute field must be :size kilobytes.',
        'string' => 'The :attribute field must be :size characters.',
        'array' => 'The :attribute field must contain :size items.',
    ],
    'gt' => [
        'numeric' => 'The :attribute field must be greater than :value.',
        'file' => 'The :attribute field must be greater than :value kilobytes.',
        'string' => 'The :attribute field must be greater than :value characters.',
        'array' => 'The :attribute field must have more than :value items.',
    ],
    'lt' => [
        'numeric' => 'The :attribute field must be less than :value.',
        'file' => 'The :attribute field must be less than :value kilobytes.',
        'string' => 'The :attribute field must be less than :value characters.',
        'array' => 'The :attribute field must have less than :value items.',
    ],
];
