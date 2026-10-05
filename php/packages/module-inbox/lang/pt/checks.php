<?php

declare(strict_types=1);

return [
    'inbox' => [
        'no_recipients' => [
            'title' => 'Formulários que não avisam ninguém',
            'found' => 'Um formulário ativo não indica nenhum destinatário que um e-mail alcançaria: nenhum, só administradores excluídos ou desativados desde então, ou endereços que não são endereços.',
            'why' => 'Cada envio é guardado e o visitante recebe o agradecimento, mas ninguém fica sabendo até alguém abrir a caixa de entrada — um pedido pode esperar dias.',
            'fix' => 'Abra o formulário, vá à aba Avisos e adicione um administrador ou um endereço. Se o formulário é lido só no painel, ignore este aviso.',
        ],
    ],
];
