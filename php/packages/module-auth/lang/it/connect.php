<?php

declare(strict_types=1);

/*
 * The page that hands a person the address and walks them through their client. It is read
 * once, by somebody who has never done this before, and often out loud over a call.
 */

return [
    'lead' => 'Un agente IA — Claude, ChatGPT, Cursor — può lavorare in questo pannello a suo nome. Gli dia l\'indirizzo qui sotto: le chiederà di accedere e poi chiederà cosa può fare.',
    'title' => 'Collegare un agente',
    'address' => 'L\'indirizzo di questo pannello per gli agenti',
    'copy' => 'Copia l\'indirizzo',
    'copied' => 'Copiato',
    'copy-failed' => 'Gli appunti non sono raggiungibili. Selezioni l\'indirizzo e lo copi a mano.',
    'copy-line' => 'Copia',
    'how' => 'Come aggiungerlo',
    'claude-title' => 'Claude',
    'claude-1' => 'Apra le impostazioni e vada su «Connectors».',
    'claude-2' => 'Prema «Add custom connector».',
    'claude-3' => 'Incolli l\'indirizzo e prema «Add».',
    'chatgpt-title' => 'ChatGPT',
    'chatgpt-1' => 'Apra le impostazioni e vada su «Connectors».',
    'chatgpt-2' => 'Prema «Add» e scelga un server MCP proprio.',
    'chatgpt-3' => 'Incolli l\'indirizzo, lasci OAuth come autenticazione e confermi.',
    'claude-code-title' => 'Claude Code',
    'claude-code-hint' => 'Una riga in un terminale, da qualsiasi progetto:',
    'codex-title' => 'Codex',
    'codex-hint' => 'Queste righe in ~/.codex/config.toml:',
    'one-click' => 'Con un clic',
    'one-click-hint' => 'Questi due prendono tutto da un link: l\'editor si apre, chiede una volta, e il server è a posto.',
    'install-in' => 'Aggiungi a :client',
    'cursor-title' => 'Cursor',
    'vscode-title' => 'VS Code',
    'next' => 'Cosa succede poi',
    'next-1' => 'Il client apre questo sito in un browser e le chiede di accedere al pannello con la sua password di sempre. La password non la vede mai.',
    'next-2' => 'Il pannello mostra chi chiede e dove va la risposta. Lì c\'è l\'interruttore «sola lettura», ed è il modo prudente di cominciare.',
    'next-3' => 'Da lì in poi l’agente agisce come lei: può ciò che può lei e nulla di più, e ogni sua chiamata viene annotata.',
    'mine' => 'Le sue connessioni',

];
