<?php

declare(strict_types=1);

return [
    'on' => 'Ativado',
    'normalise-note' => 'Funciona para os pedidos que o servidor web entrega ao site. Se a deteção continuar após a próxima execução, o servidor web responde a esse endereço por si próprio — configure lá o redirecionamento.',
    'robots-file-note' => 'O site tem um ficheiro public/robots.txt. O servidor web serve-o antes de perguntar ao site, por isso elimine-o para que a definição tenha efeito.',
    'redirect-chain' => 'Saltos antes da página: :steps',
    'title-duplicate' => '“:title” — em :count fichas',
    'redirect-broken' => ':target responde :status',
    'rule-dead' => 'O endereço responde :status',
];
