<?php

declare(strict_types=1);

return [
    'title' => 'Entrega',
    'cache' => 'Métodos de entrega e transportadoras',
    'cache_points' => 'Pontos de entrega das transportadoras',
    'carriers' => [
        'store' => 'A loja',
    ],
    'point_kinds' => [
        'branch' => 'Agência',
        'locker' => 'Cacifo',
        'store' => 'Loja',
        'pickup' => 'Ponto de recolha',
    ],
    'locality_kinds' => [
        'city' => 'Cidade',
        'town' => 'Cidade',
        'township' => 'Vila',
        'village' => 'Aldeia',
        'settlement' => 'Povoado',
        'other' => 'Localidade',
    ],
    'step' => [
        'delivery' => 'Entrega',
        'locality' => 'Cidade ou localidade',
        'locality_placeholder' => 'Comece a escrever o nome',
        'where' => 'Para onde entregar a encomenda?',
        'choose_locality' => 'Escolha uma cidade',
        'change' => 'Alterar',
        'popular' => 'Cidades populares',
        'show_methods' => 'Mostrar métodos de entrega',
        'no_methods' => 'Não há métodos de entrega disponíveis aqui',
        'free' => 'Grátis',
        'carrier_rates' => 'Segundo as tarifas da transportadora',
        'choose_point' => 'Escolha uma agência',
        'pick_up_here' => 'Levantar aqui',
        'open_until' => 'Hoje aberto até às :time',
        'closed_today' => 'Hoje fechado',
        'street' => 'Rua',
        'house' => 'Número',
        'apartment' => 'Andar',
        'note' => 'Nota para o estafeta',
        'cost' => 'Custo de entrega',
        'points' => 'Pontos: :count',
    ],
    'hints' => [
        'no_streets' => 'A entrega por estafeta não está disponível aqui, escolha outro método',
    ],
    // A street of a carrier's directory as shown: its name with the type word where the language puts it.
    'street_kinds' => [
        'street' => 'rua :name',
        'avenue' => 'avenida :name',
        'lane' => 'travessa :name',
        'square' => 'praça :name',
        'other' => ':name',
    ],
    'errors' => [
        'choose_point' => 'Escolha um ponto de recolha.',
        'point_gone' => 'O ponto de recolha já não está disponível, escolha outro.',
        'choose_street' => 'Escolha a rua da lista.',
        'street_required' => 'Indique a rua.',
        'house_required' => 'Indique o número.',
        'too_long' => 'No máximo :max caracteres.',
        'no_quote' => 'Não foi possível calcular a entrega, escolha outro método.',
    ],
    'sync' => [
        'running' => 'Já em curso, página :page',
        'errors' => [
            'no_answer' => 'A transportadora não respondeu',
            'key_rejected' => 'A transportadora recusou a chave de API',
            'bad_response' => 'A resposta da transportadora não estava no formato esperado',
            'failed' => 'A sincronização falhou do lado do site — veja o registo',
            'interrupted' => 'A sincronização foi interrompida',
        ],
    ],
    'schedule' => [
        'closed' => 'Fechado',
        'around_the_clock' => 'Aberto 24 horas',
        'until' => 'Até às :time',
        'from' => 'Abre às :time',
    ],
];
