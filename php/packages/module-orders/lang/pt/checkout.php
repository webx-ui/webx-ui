<?php

declare(strict_types=1);

/*
 * The checkout (spec §33). The words of the page, its steps and the «Thanks» come with their
 * stages; the tab «Checkout» of the settings is here now.
 */
return [
    'settings' => [
        'tab' => 'Checkout',
        'fields' => 'Contactos do comprador',
        'fields-help' => 'O nome é sempre pedido. Um pedido precisa de um telefone ou de um e-mail, por isso pelo menos um deles continua obrigatório.',
        'last-name' => 'Apelido',
        'phone' => 'Telefone',
        'email' => 'E-mail',
        'recipient' => '«O destinatário é outra pessoa»',
        'recipient-help' => 'Uma caixa sob os contactos para um pedido que outra pessoa levanta.',
        'comment' => 'Comentário',
        'comment-help' => 'Um campo onde o comprador deixa um comentário sobre o pedido.',
        'terms-card' => 'Condições',
        'terms' => 'Aceitação das condições',
        'terms-help' => 'Aviso — uma linha sob o botão; caixa — o comprador marca-a antes de fazer o pedido. A aceitação fica registada no histórico do pedido.',
        'terms-page' => 'Página das condições',
        'terms-page-help' => 'Uma página do site ou um endereço para onde a aceitação aponta.',
        'privacy-page' => 'Política de privacidade',
        'privacy-page-help' => 'Um segundo documento, se o site o tiver: como são tratados os dados pessoais.',
        'contact-needed' => 'Mantenha o telefone ou o e-mail obrigatório: um pedido precisa de um deles.',
        'values' => [
            'required' => 'Obrigatório',
            'optional' => 'Opcional',
            'hidden' => 'Oculto',
            'on' => 'Sim',
            'off' => 'Não',
            'none' => 'Nenhuma',
            'notice' => 'Aviso',
            'checkbox' => 'Caixa',
        ],
    ],
];
