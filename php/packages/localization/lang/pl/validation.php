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
    'accepted' => 'Pole :attribute musi zostać zaakceptowane.',
    'array' => 'Pole :attribute musi być listą.',
    'boolean' => 'Pole :attribute musi być tak albo nie.',
    'confirmed' => 'Pole :attribute nie zgadza się z potwierdzeniem.',
    'date' => 'Pole :attribute musi być datą.',
    'different' => 'Pola :attribute i :other muszą się różnić.',
    'email' => 'Pole :attribute musi być poprawnym adresem e-mail.',
    'exists' => 'Nie ma takiego :attribute.',
    'file' => 'Pole :attribute musi być plikiem.',
    'filled' => 'Pole :attribute nie może być puste.',
    'image' => 'Pole :attribute musi być obrazem.',
    'in' => 'Pole :attribute nie może mieć takiej wartości.',
    'integer' => 'Pole :attribute musi być liczbą całkowitą.',
    'numeric' => 'Pole :attribute musi być liczbą.',
    'present' => 'Pole :attribute musi być obecne.',
    'prohibited' => 'Pole :attribute jest niedozwolone.',
    'required' => 'Pole :attribute jest wymagane.',
    'same' => 'Pola :attribute i :other muszą być takie same.',
    'string' => 'Pole :attribute musi być tekstem.',
    'unique' => 'To :attribute jest już zajęte.',
    'uploaded' => 'Nie udało się przesłać pola :attribute.',
    'url' => 'Pole :attribute musi być poprawnym adresem.',
    'uuid' => 'Pole :attribute musi być poprawnym UUID.',
    'mimes' => 'Pole :attribute musi być plikiem typu: :values.',
    'max' => [
        'numeric' => 'Pole :attribute nie może być większe niż :max.',
        'file' => 'Pole :attribute nie może być większe niż :max kilobajtów.',
        'string' => 'Pole :attribute nie może być dłuższe niż :max znaków.',
        'array' => 'Pole :attribute nie może mieć więcej niż :max elementów.',
    ],
    'min' => [
        'numeric' => 'Pole :attribute musi wynosić co najmniej :min.',
        'file' => 'Pole :attribute musi mieć co najmniej :min kilobajtów.',
        'string' => 'Pole :attribute musi mieć co najmniej :min znaków.',
        'array' => 'Pole :attribute musi mieć co najmniej :min elementów.',
    ],
    'between' => [
        'numeric' => 'Pole :attribute musi być między :min a :max.',
        'file' => 'Pole :attribute musi mieć od :min do :max kilobajtów.',
        'string' => 'Pole :attribute musi mieć od :min do :max znaków.',
        'array' => 'Pole :attribute musi mieć od :min do :max elementów.',
    ],
    'size' => [
        'numeric' => 'Pole :attribute musi wynosić :size.',
        'file' => 'Pole :attribute musi mieć :size kilobajtów.',
        'string' => 'Pole :attribute musi mieć :size znaków.',
        'array' => 'Pole :attribute musi zawierać :size elementów.',
    ],
    'gt' => [
        'numeric' => 'Pole :attribute musi być większe niż :value.',
        'file' => 'Pole :attribute musi być większe niż :value kilobajtów.',
        'string' => 'Pole :attribute musi być dłuższe niż :value znaków.',
        'array' => 'Pole :attribute musi mieć więcej niż :value elementów.',
    ],
    'lt' => [
        'numeric' => 'Pole :attribute musi być mniejsze niż :value.',
        'file' => 'Pole :attribute musi być mniejsze niż :value kilobajtów.',
        'string' => 'Pole :attribute musi być krótsze niż :value znaków.',
        'array' => 'Pole :attribute musi mieć mniej niż :value elementów.',
    ],
];
