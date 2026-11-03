<?php

declare(strict_types=1);

return [
    'carrier' => [
        'title' => 'Nova Poshta',
    ],
    'types' => [
        'branch' => [
            'title' => 'Agência Nova Poshta',
            'description' => 'Levantamento numa agência da transportadora; paga o envio à transportadora no levantamento.',
        ],
        'locker' => [
            'title' => 'Cacifo Nova Poshta',
            'description' => 'Levantamento num cacifo da transportadora; paga o envio à transportadora no levantamento.',
        ],
        'courier' => [
            'title' => 'Estafeta Nova Poshta',
            'description' => 'A transportadora entrega a encomenda à sua porta; paga o envio à transportadora na entrega.',
        ],
    ],
    'settings' => [
        'payment_method' => 'Pagamento do envio',
        'payment_methods' => [
            'Cash' => 'Dinheiro',
            'NonCash' => 'Sem dinheiro (com contrato com a transportadora)',
        ],
        'cargo_type' => 'Tipo de carga predefinido',
        'cargo_types' => [
            'Parcel' => 'Encomenda',
            'Cargo' => 'Carga',
            'Documents' => 'Documentos',
        ],
        'weight' => 'Peso predefinido, kg',
        'description' => 'Descrição da carga predefinida',
        'description_default' => 'Mercadorias',
        'sender' => 'Remetente',
        'contact' => 'Pessoa de contacto',
        'sender_city' => 'Cidade de envio',
        'sender_point' => 'Agência de envio',
        'locker_width' => 'Cacifo: largura predefinida, cm',
        'locker_height' => 'Cacifo: altura predefinida, cm',
        'locker_length' => 'Cacifo: comprimento predefinido, cm',
    ],
    'credentials' => [
        'api_key' => 'Chave API',
    ],
    'connection' => [
        'ok' => 'Remetentes na conta: :count',
        'key_refused' => 'A transportadora não aceitou a chave API.',
        'no_answer' => 'A transportadora não respondeu.',
        'no_sender' => 'A conta da transportadora não tem remetente.',
        'no_key' => 'Introduza primeiro a chave API.',
    ],
    'waybill' => [
        'weight' => 'Peso, kg',
        'seats' => 'Volumes',
        'cargo_type' => 'Tipo de carga',
        'description' => 'Descrição da carga',
        'date' => 'Data de envio',
        'width' => 'Largura, cm',
        'height' => 'Altura, cm',
        'length' => 'Comprimento, cm',
    ],
    'errors' => [
        'no_cod' => 'A cobrança na entrega ainda não é suportada por esta transportadora.',
        'name_empty' => 'Preencha o nome e o apelido do destinatário.',
        'name_letters' => 'A transportadora aceita o nome apenas em letras ucranianas.',
        'phone' => 'A transportadora aceita um telemóvel ucraniano: +380 e nove dígitos.',
        'no_sender' => 'Escolha primeiro nas definições da transportadora o remetente, a pessoa de contacto, a cidade e a agência de envio.',
        'contact_gone' => 'A pessoa de contacto das definições já não está na conta da transportadora: escolha outra.',
        'no_town' => 'A transportadora não conhece a localidade da encomenda: associe-a na fila de localidades.',
        'no_point' => 'A encomenda não tem agência nem cacifo da transportadora.',
        'no_street' => 'A encomenda não tem rua da transportadora ou número da porta.',
        'weight' => 'O peso tem de ser maior que zero.',
        'weight_too_much' => 'O peso é maior do que o ponto aceita.',
        'seats' => 'Os volumes são um número inteiro a partir de um.',
        'locker_seats' => 'Um cacifo aceita um volume.',
        'cargo_type' => 'Escolha um tipo de carga da lista.',
        'size' => 'As medidas têm de ser maiores que zero.',
        'locker_size' => 'Um cacifo aceita até 40 × 30 × 60 cm.',
        'date' => 'A data de envio é hoje ou um dia dentro de três meses.',
        'read_only' => 'Este site usa a chave da transportadora só para leitura: as guias estão desligadas.',
        'no_ref' => 'O identificador desta guia na transportadora não está guardado: anule-a na conta da transportadora.',
        'carrier_said' => 'A transportadora recusou: :text',
    ],
    'remarks' => [
        'novaposhta_service_point' => 'Ignorados, pontos de serviço da transportadora: :count',
        'novaposhta_deny_to_select' => 'Ignorados, fechados à escolha: :count',
        'novaposhta_invalid_point' => 'Ignorados, com dados incompletos: :count',
    ],
];
