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
    'accepted' => 'Das Feld :attribute muss akzeptiert werden.',
    'array' => 'Das Feld :attribute muss eine Liste sein.',
    'boolean' => 'Das Feld :attribute muss ja oder nein sein.',
    'confirmed' => 'Das Feld :attribute stimmt nicht mit der Bestätigung überein.',
    'date' => 'Das Feld :attribute muss ein Datum sein.',
    'different' => 'Die Felder :attribute und :other müssen sich unterscheiden.',
    'email' => 'Das Feld :attribute muss eine gültige E-Mail-Adresse sein.',
    'exists' => 'So ein :attribute gibt es nicht.',
    'file' => 'Das Feld :attribute muss eine Datei sein.',
    'filled' => 'Das Feld :attribute darf nicht leer sein.',
    'image' => 'Das Feld :attribute muss ein Bild sein.',
    'in' => 'Diesen Wert kann :attribute nicht annehmen.',
    'integer' => 'Das Feld :attribute muss eine ganze Zahl sein.',
    'numeric' => 'Das Feld :attribute muss eine Zahl sein.',
    'present' => 'Das Feld :attribute muss vorhanden sein.',
    'prohibited' => 'Das Feld :attribute ist nicht erlaubt.',
    'required' => 'Das Feld :attribute ist erforderlich.',
    'same' => 'Die Felder :attribute und :other müssen übereinstimmen.',
    'string' => 'Das Feld :attribute muss Text sein.',
    'unique' => 'Dieses :attribute ist bereits vergeben.',
    'uploaded' => 'Das Feld :attribute konnte nicht hochgeladen werden.',
    'url' => 'Das Feld :attribute muss eine gültige Adresse sein.',
    'uuid' => 'Das Feld :attribute muss eine gültige UUID sein.',
    'mimes' => 'Das Feld :attribute muss eine Datei vom Typ :values sein.',
    'max' => [
        'numeric' => 'Das Feld :attribute darf nicht größer als :max sein.',
        'file' => 'Das Feld :attribute darf nicht größer als :max Kilobyte sein.',
        'string' => 'Das Feld :attribute darf nicht länger als :max Zeichen sein.',
        'array' => 'Das Feld :attribute darf nicht mehr als :max Einträge haben.',
    ],
    'min' => [
        'numeric' => 'Das Feld :attribute muss mindestens :min sein.',
        'file' => 'Das Feld :attribute muss mindestens :min Kilobyte sein.',
        'string' => 'Das Feld :attribute muss mindestens :min Zeichen lang sein.',
        'array' => 'Das Feld :attribute muss mindestens :min Einträge haben.',
    ],
    'between' => [
        'numeric' => 'Das Feld :attribute muss zwischen :min und :max liegen.',
        'file' => 'Das Feld :attribute muss zwischen :min und :max Kilobyte liegen.',
        'string' => 'Das Feld :attribute muss zwischen :min und :max Zeichen lang sein.',
        'array' => 'Das Feld :attribute muss zwischen :min und :max Einträge haben.',
    ],
    'size' => [
        'numeric' => 'Das Feld :attribute muss :size sein.',
        'file' => 'Das Feld :attribute muss :size Kilobyte groß sein.',
        'string' => 'Das Feld :attribute muss :size Zeichen lang sein.',
        'array' => 'Das Feld :attribute muss :size Einträge enthalten.',
    ],
    'gt' => [
        'numeric' => 'Das Feld :attribute muss größer als :value sein.',
        'file' => 'Das Feld :attribute muss größer als :value Kilobyte sein.',
        'string' => 'Das Feld :attribute muss länger als :value Zeichen sein.',
        'array' => 'Das Feld :attribute muss mehr als :value Einträge haben.',
    ],
    'lt' => [
        'numeric' => 'Das Feld :attribute muss kleiner als :value sein.',
        'file' => 'Das Feld :attribute muss kleiner als :value Kilobyte sein.',
        'string' => 'Das Feld :attribute muss kürzer als :value Zeichen sein.',
        'array' => 'Das Feld :attribute muss weniger als :value Einträge haben.',
    ],
];
