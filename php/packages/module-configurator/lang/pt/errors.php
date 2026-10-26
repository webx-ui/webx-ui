<?php

declare(strict_types=1);

return [
    'schema' => [
        'invalid' => 'O esquema não é um objeto JSON.',
        'version' => 'Versão de esquema desconhecida: este módulo lê a versão 1.',
    ],
    'currency' => [
        'invalid' => 'A moeda é um código ISO 4217 de três letras maiúsculas, como UAH ou EUR.',
    ],
    'pricing' => [
        'round' => 'O passo de arredondamento é um número maior que 0.',
    ],
    'discount' => [
        'percent' => 'O desconto é uma percentagem maior que 0 e menor que 100.',
        'until' => 'O último dia do desconto é uma data AAAA-MM-DD.',
    ],
    'steps' => [
        'empty' => 'Um configurador precisa de pelo menos um passo.',
    ],
    'key' => [
        'invalid' => 'A chave «:key» não é a-z, 0-9 e _ começando por uma letra.',
        'duplicate' => 'A chave «:key» está ocupada: passos e campos de medidas partilham chaves.',
    ],
    'type' => [
        'unknown' => 'Tipo de passo desconhecido: cards, swatches, buttons ou dimensions.',
    ],
    'options' => [
        'empty' => 'Um passo de escolha precisa de pelo menos uma opção.',
    ],
    'fields' => [
        'empty' => 'Um passo de medidas precisa de pelo menos um campo.',
    ],
    'value' => [
        'invalid' => 'O valor «:value» não é um slug: a-z, 0-9, - e _.',
        'duplicate' => 'O valor «:value» aparece duas vezes neste passo.',
    ],
    'label' => [
        'missing' => 'É preciso um nome no idioma predefinido (:locale).',
    ],
    'text' => [
        'invalid' => 'Um texto é uma cadeia ou um conjunto de idiomas.',
    ],
    'image' => [
        'missing' => 'Escolha uma imagem da mediateca.',
        'unknown' => 'A mediateca não tem o ficheiro «:key».',
    ],
    'swatch' => [
        'invalid' => 'Uma amostra é #rgb, #rrggbb ou um degradê de cores hex.',
    ],
    'price' => [
        'invalid' => 'Um preço é um número.',
        'per_m2' => 'Um preço por m² precisa de área: dê às opções de um passo uma fórmula de área.',
    ],
    'area' => [
        'main_count' => 'Só as opções de um passo podem ter fórmula de área.',
        'missing' => 'Cada opção do passo principal precisa de uma fórmula de área.',
        'syntax' => 'A fórmula está errada no carácter :position: :reason.',
        'unknown_key' => 'A fórmula usa «:key», que não é um campo de medidas.',
    ],
    'range' => [
        'invalid' => 'O intervalo está errado: min acima de max ou um passo não maior que 0.',
    ],
    'limits' => [
        'unknown_field' => 'limits indica «:key», que não é um campo de medidas.',
    ],
    'defaults' => [
        'unknown_field' => 'defaults indica «:key», que não é um campo de medidas.',
    ],
    'condition' => [
        'invalid' => 'Uma condição é um passo e os valores com que é escolhido.',
        'unknown_step' => 'Não existe o passo «:step».',
        'forward' => 'O passo «:step» não está acima deste: uma condição só olha para cima.',
        'not_choice' => 'O passo «:step» é de medidas: uma condição indica um passo com opções.',
        'unknown_value' => 'O passo «:step» não tem a opção «:value».',
    ],
    'initial' => [
        'unknown' => 'A escolha inicial «:key» não é um passo, um campo nem um dos seus valores.',
    ],
    'previews' => [
        'condition' => 'Uma regra de imagem indica um passo ou valor que não existe.',
    ],
    'values' => [
        'range' => 'A medida «:key» vai de :min a :max.',
        'number' => 'A medida «:key» é um número.',
    ],
];
