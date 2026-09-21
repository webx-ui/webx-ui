<?php

declare(strict_types=1);

/*
 * The page that hands a person the address and walks them through their client. It is read
 * once, by somebody who has never done this before, and often out loud over a call.
 */

return [
    'lead' => 'Um agente de IA — Claude, ChatGPT, Cursor — pode trabalhar neste painel em seu nome. Dê-lhe o endereço abaixo: vai pedir-lhe para entrar e depois perguntar o que pode fazer.',
    'title' => 'Ligar um agente',
    'address' => 'O endereço deste painel para agentes',
    'copy' => 'Copiar o endereço',
    'copied' => 'Copiado',
    'copy-failed' => 'A área de transferência não está acessível. Selecione o endereço e copie-o à mão.',
    'copy-line' => 'Copiar',
    'how' => 'Como adicionar',
    'claude-title' => 'Claude',
    'claude-1' => 'Abra as definições e vá a «Connectors».',
    'claude-2' => 'Carregue em «Add custom connector».',
    'claude-3' => 'Cole o endereço e carregue em «Add».',
    'chatgpt-title' => 'ChatGPT',
    'chatgpt-1' => 'Abra as definições e vá a «Connectors».',
    'chatgpt-2' => 'Carregue em «Add» e escolha um servidor MCP próprio.',
    'chatgpt-3' => 'Cole o endereço, deixe OAuth como autenticação e confirme.',
    'claude-code-title' => 'Claude Code',
    'claude-code-hint' => 'Uma linha num terminal, a partir de qualquer projeto:',
    'codex-title' => 'Codex',
    'codex-hint' => 'Estas linhas em ~/.codex/config.toml:',
    'one-click' => 'Com um clique',
    'one-click-hint' => 'Estes dois aceitam tudo por uma ligação: o editor abre, pergunta uma vez, e o servidor fica posto.',
    'install-in' => 'Adicionar ao :client',
    'cursor-title' => 'Cursor',
    'vscode-title' => 'VS Code',
    'next' => 'O que acontece a seguir',
    'next-1' => 'O cliente abre este site num navegador e pede-lhe para entrar no painel com a sua palavra-passe do costume. Nunca a vê.',
    'next-2' => 'O painel mostra quem está a pedir e para onde vai a resposta. Aí está o interruptor «apenas leitura», e começar com ele é o caminho seguro.',
    'next-3' => 'A partir daí o agente age como você: pode o que você pode e nada mais, e cada chamada sua fica registada.',
    'mine' => 'As suas ligações',

];
