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
    'accepted' => ':attribute alanı kabul edilmelidir.',
    'array' => ':attribute alanı bir liste olmalıdır.',
    'boolean' => ':attribute alanı evet ya da hayır olmalıdır.',
    'confirmed' => ':attribute alanı doğrulamasıyla eşleşmiyor.',
    'date' => ':attribute alanı bir tarih olmalıdır.',
    'different' => ':attribute ve :other alanları farklı olmalıdır.',
    'email' => ':attribute alanı geçerli bir e-posta adresi olmalıdır.',
    'exists' => 'Böyle bir :attribute yok.',
    'file' => ':attribute alanı bir dosya olmalıdır.',
    'filled' => ':attribute alanı boş olamaz.',
    'image' => ':attribute alanı bir görsel olmalıdır.',
    'in' => ':attribute alanı bu değeri alamaz.',
    'integer' => ':attribute alanı tam sayı olmalıdır.',
    'numeric' => ':attribute alanı sayı olmalıdır.',
    'present' => ':attribute alanı bulunmalıdır.',
    'prohibited' => ':attribute alanına izin verilmiyor.',
    'required' => ':attribute alanı zorunludur.',
    'same' => ':attribute ve :other alanları aynı olmalıdır.',
    'string' => ':attribute alanı metin olmalıdır.',
    'unique' => 'Bu :attribute zaten alınmış.',
    'uploaded' => ':attribute alanı yüklenemedi.',
    'url' => ':attribute alanı geçerli bir adres olmalıdır.',
    'uuid' => ':attribute alanı geçerli bir UUID olmalıdır.',
    'mimes' => ':attribute alanı şu türde bir dosya olmalıdır: :values.',
    'max' => [
        'numeric' => ':attribute alanı :max değerinden büyük olamaz.',
        'file' => ':attribute alanı :max kilobayttan büyük olamaz.',
        'string' => ':attribute alanı :max karakterden uzun olamaz.',
        'array' => ':attribute alanı :max ögeden fazlasını içeremez.',
    ],
    'min' => [
        'numeric' => ':attribute alanı en az :min olmalıdır.',
        'file' => ':attribute alanı en az :min kilobayt olmalıdır.',
        'string' => ':attribute alanı en az :min karakter olmalıdır.',
        'array' => ':attribute alanı en az :min öge içermelidir.',
    ],
    'between' => [
        'numeric' => ':attribute alanı :min ile :max arasında olmalıdır.',
        'file' => ':attribute alanı :min ile :max kilobayt arasında olmalıdır.',
        'string' => ':attribute alanı :min ile :max karakter arasında olmalıdır.',
        'array' => ':attribute alanı :min ile :max öge arasında içermelidir.',
    ],
    'size' => [
        'numeric' => ':attribute alanı :size olmalıdır.',
        'file' => ':attribute alanı :size kilobayt olmalıdır.',
        'string' => ':attribute alanı :size karakter olmalıdır.',
        'array' => ':attribute alanı :size öge içermelidir.',
    ],
    'gt' => [
        'numeric' => ':attribute alanı :value değerinden büyük olmalıdır.',
        'file' => ':attribute alanı :value kilobayttan büyük olmalıdır.',
        'string' => ':attribute alanı :value karakterden uzun olmalıdır.',
        'array' => ':attribute alanı :value ögeden fazla içermelidir.',
    ],
    'lt' => [
        'numeric' => ':attribute alanı :value değerinden küçük olmalıdır.',
        'file' => ':attribute alanı :value kilobayttan küçük olmalıdır.',
        'string' => ':attribute alanı :value karakterden kısa olmalıdır.',
        'array' => ':attribute alanı :value ögeden az içermelidir.',
    ],
];
