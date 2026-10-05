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
        'notification' => [
            'title' => 'Notificações de envios que não saíram',
            'found' => 'Cartas sobre envios falharam ou aguardam há muito tempo na fila.',
            'why' => 'As pessoas indicadas no formulário não sabem de pedidos que o painel já tem, e o site não o diz.',
            'fix' => 'Falharam: corrija as configurações de e-mail, reinicie o worker da fila (php artisan queue:restart) para que as leia e envie a notificação novamente a partir do envio. Aguardando: inicie um worker da fila.',
        ],
    ],
];
