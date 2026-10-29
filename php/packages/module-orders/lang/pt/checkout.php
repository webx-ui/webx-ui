<?php

declare(strict_types=1);

/*
 * The checkout (spec §33): the page, its steps, the column of the totals, and the tab «Checkout»
 * of the settings.
 */
return [
    'page' => [
        'title' => 'Finalizar compra',
        'empty' => 'O seu carrinho está vazio.',
        'shopping' => 'Voltar à loja',
        'open-cart' => 'Abrir o carrinho',
    ],
    'steps' => [
        'contacts' => 'Contactos',
        'comment' => 'Comentário',
        'edit' => 'Alterar',
        'save' => 'Guardar',
        'signed-in' => 'Sessão iniciada como :name',
    ],
    'fields' => [
        'first-name' => 'Nome',
        'last-name' => 'Apelido',
        'phone' => 'Telefone',
        'phone-hint' => 'Por exemplo, +800 1234 5678',
        'email' => 'E-mail',
        'recipient' => 'O destinatário é outra pessoa',
        'recipient-legend' => 'Destinatário',
        'recipient-first-name' => 'Nome do destinatário',
        'recipient-last-name' => 'Apelido do destinatário',
        'recipient-phone' => 'Telefone do destinatário',
        'comment' => 'Comentário sobre a encomenda',
    ],
    'summary' => [
        'title' => 'A sua encomenda',
        'units' => ':count artigo por|:count artigos por',
        'discount' => 'Desconto',
        'payable' => 'A pagar',
        'lines' => 'Conteúdo da encomenda',
        'price-down' => 'agora mais barato',
        'price-up' => 'agora mais caro',
        'place' => 'Confirmar a encomenda',
    ],
    'terms' => [
        'agree' => 'Aceito as condições',
        'notice' => 'Ao carregar no botão, aceita as condições',
        'terms' => 'Condições de venda',
        'privacy' => 'Política de privacidade',
    ],
    'errors' => [
        'required' => 'Preencha «:field».',
        'phone-or-email' => 'Indique um telefone ou um e-mail.',
        'phone' => 'Um telefone é um + e algarismos, por exemplo +800 1234 5678.',
        'email' => 'Verifique o e-mail.',
        'terms' => 'Aceite as condições para fazer a encomenda.',
        'payable-changed' => 'O valor mudou: era :was, agora é :now. Verifique a encomenda e confirme de novo.',
        'not-orderable' => 'Alguns artigos do carrinho não podem ser encomendados. Abra o carrinho.',
    ],
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
