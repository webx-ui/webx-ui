<?php

declare(strict_types=1);

return [
    'gateway' => [
        'title' => 'LiqPay',
        'mode' => 'Modo',
        'modes' => [
            'test' => 'Teste',
            'live' => 'Produção',
        ],
        'test_public_key' => 'Chave pública, teste',
        'live_public_key' => 'Chave pública, produção',
        'test_private_key' => 'Chave privada, teste',
        'live_private_key' => 'Chave privada, produção',
        'language' => 'Idioma da página',
        'language_help' => 'O LiqPay mostra a sua página apenas em ucraniano e inglês.',
        'languages' => [
            'auto' => 'Pelo idioma da encomenda',
            'uk' => 'Ucraniano',
            'en' => 'Inglês',
        ],
        'expires_minutes' => 'Minutos em que a página de pagamento funciona',
    ],
    'paytypes' => [
        'card' => 'Cartão',
        'apay' => 'Apple Pay',
        'gpay' => 'Google Pay',
        'privat24' => 'Privat24',
        'qr' => 'Código QR',
        'moment_part' => 'Prestações instantâneas',
        'paypart' => 'Pagamento em partes',
        'paypart_help' => 'Ative primeiro o serviço na área do LiqPay',
        'hold_cards_only' => 'A retenção funciona apenas para pagamentos com cartão',
        'hold_needs_choice' => 'Com retenção, escolha o que a página oferece: sem escolha mostra também as prestações',
    ],
    'connection' => [
        'ok' => 'O LiqPay aceitou as chaves',
        'no_keys' => 'Introduza as duas chaves deste modo',
        'wrong_mode' => 'Estas chaves não correspondem ao modo escolhido',
        'private_key' => 'A chave privada não corresponde',
        'public_key' => 'A chave pública não foi encontrada',
        'no_answer' => 'O LiqPay não respondeu',
    ],
    'status' => [
        'wait_accept' => 'O LiqPay ainda não verificou a loja: o dinheiro chega após a verificação',
        'wait_reserve' => 'O pagamento foi concluído; o dinheiro está reservado para um reembolso anterior',
        'subscribed' => 'O LiqPay indica uma subscrição: o site não as tem',
        'unsubscribed' => 'O LiqPay indica uma subscrição cancelada: o site não as tem',
        'unknown' => 'Estado desconhecido do LiqPay: :status',
    ],
    'errors' => [
        '9859' => 'O cartão não tem saldo suficiente',
        'limit' => 'O banco recusou: foi atingido um limite do cartão',
        'expired' => 'O prazo de pagamento terminou',
        'wait_info' => 'O LiqPay aguarda mais dados do comprador',
        'err_amount_hold' => 'O valor excede o montante retido',
        'payment_err_status' => 'O LiqPay não o permite no estado atual do pagamento',
        'order_id_duplicate' => 'O LiqPay já tem um pagamento pago com este número',
        'invalid_signature' => 'A assinatura não corresponde: verifique a chave privada',
        'public_key_not_found' => 'O LiqPay não conhece esta chave pública',
        'payment_not_found' => 'O LiqPay não tem esse pagamento: o comprador não o iniciou',
        'err_wrong_currency' => 'O LiqPay não aceita esta moeda',
    ],
];
