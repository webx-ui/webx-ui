<?php

declare(strict_types=1);

return [
    'title' => 'Entrega',
    'cache' => 'Métodos de entrega e transportadoras',
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
    'errors' => [
        'choose_point' => 'Escolha um ponto de recolha.',
        'point_gone' => 'O ponto de recolha já não está disponível, escolha outro.',
        'choose_street' => 'Escolha a rua da lista.',
        'street_required' => 'Indique a rua.',
        'house_required' => 'Indique o número.',
        'too_long' => 'No máximo :max caracteres.',
        'no_quote' => 'Não foi possível calcular a entrega, escolha outro método.',
    ],
];
