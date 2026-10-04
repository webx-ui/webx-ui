<?php

declare(strict_types=1);

return [
    'empty' => 'O endereço, a pergunta e a resposta são obrigatórios.',
    'question-long' => 'A pergunta tem mais de 1000 caracteres.',
    'foreign-host' => 'O endereço está em outro site.',
    'duplicate' => 'Esta pergunta já está na página.',
    'redirected' => ':from redireciona; o seu destino :to é usado no lugar.',
    'unreadable' => 'Não foi possível ler o arquivo como CSV ou XLSX.',
    'has-faq' => 'Esta regra tem FAQ, e só uma regra para um endereço exato pode mantê-lo. Remova primeiro as perguntas.',
    'question-missing' => 'A resposta não tem pergunta.',
    'answer-missing' => 'A pergunta não tem resposta.',

    // The panel's view of it (§18.5).
    'tab' => 'FAQ',
    'meta' => 'Meta tags',
    'help' => 'As perguntas desta página. Vão para a sua marcação FAQPage e para a página onde o modelo exibe o FAQ.',
    'exact-only' => 'Só uma regra para um endereço exato tem FAQ.',
    'question' => 'Pergunta',
    'answer' => 'Resposta',
    'add' => 'Adicionar uma pergunta',
    'remove' => 'Remover a pergunta',
    'drag' => 'Mover',
    'none' => 'Ainda não há perguntas.',
    'column' => 'FAQ',
    'with-faq' => 'Só com FAQ',
    'import' => 'Importar FAQ',
    'export-csv' => 'Exportar FAQ como CSV',
    'export-xlsx' => 'Exportar FAQ como XLSX',
    'import-title' => 'Importar FAQ',
    'import-help' => 'Um arquivo CSV ou XLSX com as colunas endereço, pergunta e resposta (HTML ou texto), no idioma do endereço. Um endereço sem regra exata recebe uma com as meta tags vazias.',
    'mode-replace' => 'Substituir o FAQ dos endereços do arquivo',
    'mode-append' => 'Adicionar às perguntas existentes',
    'imported' => 'Importado. Endereços: :count',
    'result-addresses' => 'Endereços',
    'result-questions' => 'Perguntas',
    'result-created' => 'Regras novas',
    'result-replaced' => 'Substituídas',
    'result-appended' => 'Ampliadas',
    'result-errors' => 'Erros',
];
