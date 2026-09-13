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
    'accepted' => 'Нужно согласиться с полем «:attribute».',
    'array' => 'Поле «:attribute» должно быть списком.',
    'boolean' => 'Поле «:attribute» должно быть да или нет.',
    'confirmed' => 'Поле «:attribute» не совпадает с подтверждением.',
    'date' => 'Поле «:attribute» должно быть датой.',
    'different' => 'Поля «:attribute» и «:other» должны отличаться.',
    'email' => 'Поле «:attribute» должно быть корректным адресом почты.',
    'exists' => 'Такого значения в поле «:attribute» нет.',
    'file' => 'Поле «:attribute» должно быть файлом.',
    'filled' => 'Поле «:attribute» не может быть пустым.',
    'image' => 'Поле «:attribute» должно быть изображением.',
    'in' => 'Такого значения у поля «:attribute» быть не может.',
    'integer' => 'Поле «:attribute» должно быть целым числом.',
    'numeric' => 'Поле «:attribute» должно быть числом.',
    'present' => 'Поле «:attribute» должно присутствовать.',
    'prohibited' => 'Поле «:attribute» заполнять нельзя.',
    'required' => 'Поле «:attribute» обязательно.',
    'same' => 'Поля «:attribute» и «:other» должны совпадать.',
    'string' => 'Поле «:attribute» должно быть текстом.',
    'unique' => 'Такое значение поля «:attribute» уже занято.',
    'uploaded' => 'Не удалось загрузить поле «:attribute».',
    'url' => 'Поле «:attribute» должно быть корректной ссылкой.',
    'uuid' => 'Поле «:attribute» должно быть корректным UUID.',
    'mimes' => 'Поле «:attribute» должно быть файлом типа: :values.',
    'max' => [
        'numeric' => 'Поле «:attribute» не может быть больше :max.',
        'file' => 'Поле «:attribute» не может быть больше :max Кб.',
        'string' => 'Поле «:attribute» не может быть длиннее :max символов.',
        'array' => 'В поле «:attribute» не может быть больше :max элементов.',
    ],
    'min' => [
        'numeric' => 'Поле «:attribute» не может быть меньше :min.',
        'file' => 'Поле «:attribute» не может быть меньше :min Кб.',
        'string' => 'Поле «:attribute» не может быть короче :min символов.',
        'array' => 'В поле «:attribute» должно быть не меньше :min элементов.',
    ],
    'between' => [
        'numeric' => 'Поле «:attribute» должно быть от :min до :max.',
        'file' => 'Поле «:attribute» должно быть от :min до :max Кб.',
        'string' => 'Поле «:attribute» должно быть от :min до :max символов.',
        'array' => 'В поле «:attribute» должно быть от :min до :max элементов.',
    ],
    'size' => [
        'numeric' => 'Поле «:attribute» должно быть равно :size.',
        'file' => 'Поле «:attribute» должно весить :size Кб.',
        'string' => 'Поле «:attribute» должно быть длиной :size символов.',
        'array' => 'В поле «:attribute» должно быть :size элементов.',
    ],
    'gt' => [
        'numeric' => 'Поле «:attribute» должно быть больше :value.',
        'file' => 'Поле «:attribute» должно быть больше :value Кб.',
        'string' => 'Поле «:attribute» должно быть длиннее :value символов.',
        'array' => 'В поле «:attribute» должно быть больше :value элементов.',
    ],
    'lt' => [
        'numeric' => 'Поле «:attribute» должно быть меньше :value.',
        'file' => 'Поле «:attribute» должно быть меньше :value Кб.',
        'string' => 'Поле «:attribute» должно быть короче :value символов.',
        'array' => 'В поле «:attribute» должно быть меньше :value элементов.',
    ],
];
