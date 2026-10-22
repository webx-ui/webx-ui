<?php

declare(strict_types=1);

return [
    'title' => 'Índice de pesquisa',
    'lead' => 'O catálogo pesquisa, filtra e conta com o Manticore. Quando ele não responde, o painel responde a partir da base de dados.',
    'refresh' => 'Atualizar',
    'load-failed' => 'Não foi possível carregar o estado do índice.',

    'connection' => 'Servidor',
    'address' => 'Endereço',
    'prefix' => 'Prefixo das tabelas',
    'version' => 'Versão',
    'available' => 'Responde',
    'unavailable' => 'Não responde',

    'tables' => 'Tabelas',
    'language' => 'Idioma',
    'table' => 'Tabela',
    'in-index' => 'No índice',
    'in-database' => 'Na base de dados',
    'state' => 'Estado',
    'state-ready' => 'Atualizada',
    'state-stale' => 'Esquema desatualizado',
    'state-missing' => 'Em falta',
    'filling' => 'Uma reconstrução está a preencher a tabela ao lado.',
    'filled' => 'Uma reconstrução está a preencher a tabela ao lado: :done de :total',
    'no-tables' => 'O servidor não disse que tabelas tem.',

    'queue' => 'Fila',
    'waiting' => 'À espera de serem escritos',
    'oldest' => 'O mais antigo desde',
    'queue-empty' => 'Todos os produtos guardados estão no índice.',

    'outdated' => 'O índice não corresponde ao esquema que o catálogo escreve agora',
    'outdated-text' => 'Entretanto, a loja responde a partir das tabelas antigas, mas o que lhes falta — um filtro novo, códigos — não é pesquisado. Uma reconstrução preenche tabelas novas ao lado das ativas e troca-as; num catálogo grande são minutos de carga, por isso escolha a altura.',
    'rebuild' => 'Reconstruir',
    'rebuild-again' => 'Reconstruir de novo',
    'rebuild-title' => 'Reconstruir o índice de pesquisa?',
    'rebuild-text' => 'Todos os produtos são escritos de novo em tabelas novas, que depois substituem as ativas. Entretanto, a loja continua a funcionar com as tabelas antigas.',
    'rebuild-busy' => 'Já está em curso uma reconstrução.',
    'rebuild-locked' => 'Está em curso outra reconstrução — foi iniciada a partir da consola. Inicie esta quando ela terminar.',
    'rebuild-console' => 'Está em curso uma reconstrução iniciada a partir da consola: o botão volta quando ela terminar.',
    'rebuild-queued' => 'À espera do processador da fila',
    'rebuild-running' => 'A reconstruir: :done de :total',
    'rebuild-done' => 'Reconstruído: :done produtos',
    'rebuild-failed' => 'A reconstrução falhou',
    'rebuild-stalled' => 'Não há notícias da reconstrução há um quarto de hora: o processador da fila não está a correr ou parou. Inicie-a de novo quando o processador estiver a correr.',
    'rebuild-started' => 'A reconstrução está na fila.',
    'cancel' => 'Cancelar',
];
