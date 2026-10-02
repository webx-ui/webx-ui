<?php

declare(strict_types=1);

return [
    'tab' => 'Auditoria',
    'base-url' => 'Endereço a auditar',
    'base-url-help' => 'Vazio significa APP_URL. A auditoria pede ao site as suas páginas neste endereço.',
    'resolve-to' => 'Ligar a',
    'resolve-to-help' => 'Um endereço IP ou um host ao qual abrir a ligação, mantendo o nome público no pedido. Vazio significa o que o DNS responder. Para Docker e servidores atrás de NAT.',
    'other-hosts' => 'Outros endereços deste site',
    'other-hosts-help' => 'Ambientes de desenvolvimento, de testes e domínios antigos, um por linha. Um link para qualquer um deles é um erro onde quer que seja encontrado.',
];
