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
    'accepted' => 'O campo :attribute tem de ser aceite.',
    'array' => 'O campo :attribute tem de ser uma lista.',
    'boolean' => 'O campo :attribute tem de ser sim ou não.',
    'confirmed' => 'O campo :attribute não corresponde à confirmação.',
    'date' => 'O campo :attribute tem de ser uma data.',
    'different' => 'Os campos :attribute e :other têm de ser diferentes.',
    'email' => 'O campo :attribute tem de ser um endereço de e-mail válido.',
    'exists' => 'Não existe esse :attribute.',
    'file' => 'O campo :attribute tem de ser um ficheiro.',
    'filled' => 'O campo :attribute não pode estar vazio.',
    'image' => 'O campo :attribute tem de ser uma imagem.',
    'in' => 'O campo :attribute não pode ter esse valor.',
    'integer' => 'O campo :attribute tem de ser um número inteiro.',
    'numeric' => 'O campo :attribute tem de ser um número.',
    'present' => 'O campo :attribute tem de estar presente.',
    'prohibited' => 'O campo :attribute não é permitido.',
    'required' => 'O campo :attribute é obrigatório.',
    'same' => 'Os campos :attribute e :other têm de coincidir.',
    'string' => 'O campo :attribute tem de ser texto.',
    'unique' => 'Esse :attribute já está em uso.',
    'uploaded' => 'Não foi possível enviar o campo :attribute.',
    'url' => 'O campo :attribute tem de ser um endereço válido.',
    'uuid' => 'O campo :attribute tem de ser um UUID válido.',
    'mimes' => 'O campo :attribute tem de ser um ficheiro do tipo: :values.',
    'max' => [
        'numeric' => 'O campo :attribute não pode ser maior do que :max.',
        'file' => 'O campo :attribute não pode ter mais de :max kilobytes.',
        'string' => 'O campo :attribute não pode ter mais de :max caracteres.',
        'array' => 'O campo :attribute não pode ter mais de :max itens.',
    ],
    'min' => [
        'numeric' => 'O campo :attribute tem de ser pelo menos :min.',
        'file' => 'O campo :attribute tem de ter pelo menos :min kilobytes.',
        'string' => 'O campo :attribute tem de ter pelo menos :min caracteres.',
        'array' => 'O campo :attribute tem de ter pelo menos :min itens.',
    ],
    'between' => [
        'numeric' => 'O campo :attribute tem de estar entre :min e :max.',
        'file' => 'O campo :attribute tem de ter entre :min e :max kilobytes.',
        'string' => 'O campo :attribute tem de ter entre :min e :max caracteres.',
        'array' => 'O campo :attribute tem de ter entre :min e :max itens.',
    ],
    'size' => [
        'numeric' => 'O campo :attribute tem de ser :size.',
        'file' => 'O campo :attribute tem de ter :size kilobytes.',
        'string' => 'O campo :attribute tem de ter :size caracteres.',
        'array' => 'O campo :attribute tem de conter :size itens.',
    ],
    'gt' => [
        'numeric' => 'O campo :attribute tem de ser maior do que :value.',
        'file' => 'O campo :attribute tem de ter mais de :value kilobytes.',
        'string' => 'O campo :attribute tem de ter mais de :value caracteres.',
        'array' => 'O campo :attribute tem de ter mais de :value itens.',
    ],
    'lt' => [
        'numeric' => 'O campo :attribute tem de ser menor do que :value.',
        'file' => 'O campo :attribute tem de ter menos de :value kilobytes.',
        'string' => 'O campo :attribute tem de ter menos de :value caracteres.',
        'array' => 'O campo :attribute tem de ter menos de :value itens.',
    ],
];
