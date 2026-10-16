<?php

declare(strict_types=1);

return [
    'name' => 'Nome',
    'name-help' => 'Só para administradores, nunca aparece no site.',
    'place' => 'Local',
    'place-help' => 'Onde o código é impresso na página. Locais próprios são declarados em config/webx-snippets.php.',
    'code' => 'Código',
    'code-help' => 'Cole exatamente o que o serviço forneceu, sem <html>, <head> e <body>. document.write não funciona em um código que espera consentimento.',
    'settings' => 'Configurações',
    'enabled' => 'Ativo',
    'enabled-help' => 'Um código ativo está no site assim que é salvo.',
    'consent' => 'Consentimento de cookies',
    'consent-help' => 'Tudo exceto «Necessários» só é impresso depois que o visitante aceita essa categoria.',
    'on-stage' => 'Também em dev e stage',
    'on-stage-help' => 'Desligado: o código é impresso só em produção, uma cópia do site não conta visitas.',
    'coverage' => 'Onde é impresso',
    'only-paths' => 'Só em',
    'only-paths-help' => 'Caminhos sem o idioma, * para qualquer coisa: /, /blog/*. Vazio é o site inteiro.',
    'except-paths' => 'Exceto',
    'except-paths-help' => 'Caminhos onde o código nunca é impresso, como /blog/drafts/*.',
    'locales' => 'Idiomas',
    'locales-help' => 'Nada marcado significa todos os idiomas.',
    'history' => 'Histórico',
    'position' => 'Ordem',
];
