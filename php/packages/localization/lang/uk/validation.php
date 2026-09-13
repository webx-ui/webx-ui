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
    'accepted' => 'Потрібно погодитися з полем «:attribute».',
    'array' => 'Поле «:attribute» має бути списком.',
    'boolean' => 'Поле «:attribute» має бути так або ні.',
    'confirmed' => 'Поле «:attribute» не збігається з підтвердженням.',
    'date' => 'Поле «:attribute» має бути датою.',
    'different' => 'Поля «:attribute» і «:other» мають відрізнятися.',
    'email' => 'Поле «:attribute» має бути коректною адресою пошти.',
    'exists' => 'Такого значення в полі «:attribute» немає.',
    'file' => 'Поле «:attribute» має бути файлом.',
    'filled' => 'Поле «:attribute» не може бути порожнім.',
    'image' => 'Поле «:attribute» має бути зображенням.',
    'in' => 'Такого значення поле «:attribute» мати не може.',
    'integer' => 'Поле «:attribute» має бути цілим числом.',
    'numeric' => 'Поле «:attribute» має бути числом.',
    'present' => 'Поле «:attribute» має бути присутнім.',
    'prohibited' => 'Поле «:attribute» заповнювати не можна.',
    'required' => 'Поле «:attribute» обовʼязкове.',
    'same' => 'Поля «:attribute» і «:other» мають збігатися.',
    'string' => 'Поле «:attribute» має бути текстом.',
    'unique' => 'Таке значення поля «:attribute» вже зайняте.',
    'uploaded' => 'Не вдалося завантажити поле «:attribute».',
    'url' => 'Поле «:attribute» має бути коректним посиланням.',
    'uuid' => 'Поле «:attribute» має бути коректним UUID.',
    'mimes' => 'Поле «:attribute» має бути файлом типу: :values.',
    'max' => [
        'numeric' => 'Поле «:attribute» не може бути більшим за :max.',
        'file' => 'Поле «:attribute» не може бути більшим за :max Кб.',
        'string' => 'Поле «:attribute» не може бути довшим за :max символів.',
        'array' => 'У полі «:attribute» не може бути більше ніж :max елементів.',
    ],
    'min' => [
        'numeric' => 'Поле «:attribute» не може бути меншим за :min.',
        'file' => 'Поле «:attribute» не може бути меншим за :min Кб.',
        'string' => 'Поле «:attribute» не може бути коротшим за :min символів.',
        'array' => 'У полі «:attribute» має бути щонайменше :min елементів.',
    ],
    'between' => [
        'numeric' => 'Поле «:attribute» має бути від :min до :max.',
        'file' => 'Поле «:attribute» має бути від :min до :max Кб.',
        'string' => 'Поле «:attribute» має бути від :min до :max символів.',
        'array' => 'У полі «:attribute» має бути від :min до :max елементів.',
    ],
    'size' => [
        'numeric' => 'Поле «:attribute» має дорівнювати :size.',
        'file' => 'Поле «:attribute» має важити :size Кб.',
        'string' => 'Поле «:attribute» має бути завдовжки :size символів.',
        'array' => 'У полі «:attribute» має бути :size елементів.',
    ],
    'gt' => [
        'numeric' => 'Поле «:attribute» має бути більшим за :value.',
        'file' => 'Поле «:attribute» має бути більшим за :value Кб.',
        'string' => 'Поле «:attribute» має бути довшим за :value символів.',
        'array' => 'У полі «:attribute» має бути більше ніж :value елементів.',
    ],
    'lt' => [
        'numeric' => 'Поле «:attribute» має бути меншим за :value.',
        'file' => 'Поле «:attribute» має бути меншим за :value Кб.',
        'string' => 'Поле «:attribute» має бути коротшим за :value символів.',
        'array' => 'У полі «:attribute» має бути менше ніж :value елементів.',
    ],
];
