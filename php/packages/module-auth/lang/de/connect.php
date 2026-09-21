<?php

declare(strict_types=1);

/*
 * The page that hands a person the address and walks them through their client. It is read
 * once, by somebody who has never done this before, and often out loud over a call.
 */

return [
    'lead' => 'Ein KI-Agent — Claude, ChatGPT, Cursor — kann in diesem Panel in Ihrem Namen arbeiten. Geben Sie ihm die Adresse unten; er bittet Sie um die Anmeldung und fragt dann, was er darf.',
    'title' => 'Einen Agenten verbinden',
    'address' => 'Die Adresse dieses Panels für Agenten',
    'copy' => 'Adresse kopieren',
    'copied' => 'Kopiert',
    'copy-failed' => 'Die Zwischenablage ist nicht erreichbar. Markieren Sie die Adresse und kopieren Sie sie von Hand.',
    'copy-line' => 'Kopieren',
    'how' => 'So fügt man ihn hinzu',
    'claude-title' => 'Claude',
    'claude-1' => 'Öffnen Sie die Einstellungen und gehen Sie zu „Connectors“.',
    'claude-2' => 'Drücken Sie „Add custom connector“.',
    'claude-3' => 'Fügen Sie die Adresse ein und drücken Sie „Add“.',
    'chatgpt-title' => 'ChatGPT',
    'chatgpt-1' => 'Öffnen Sie die Einstellungen und gehen Sie zu „Connectors“.',
    'chatgpt-2' => 'Drücken Sie „Add“ und wählen Sie einen eigenen MCP-Server.',
    'chatgpt-3' => 'Fügen Sie die Adresse ein, lassen Sie OAuth stehen und bestätigen Sie.',
    'claude-code-title' => 'Claude Code',
    'claude-code-hint' => 'Eine Zeile im Terminal, aus jedem Projekt:',
    'codex-title' => 'Codex',
    'codex-hint' => 'Diese Zeilen in ~/.codex/config.toml:',
    'one-click' => 'Mit einem Klick',
    'one-click-hint' => 'Diese beiden nehmen alles über einen Link: der Editor öffnet sich, fragt einmal, und der Server steht.',
    'install-in' => 'Zu :client hinzufügen',
    'cursor-title' => 'Cursor',
    'vscode-title' => 'VS Code',
    'next' => 'Was danach passiert',
    'next-1' => 'Der Client öffnet diese Seite im Browser und bittet Sie, sich am Panel anzumelden — mit Ihrem üblichen Passwort. Er sieht das Passwort nie.',
    'next-2' => 'Das Panel zeigt, wer fragt und wohin die Antwort geht. Dort steht auch der Schalter „nur lesen“, und damit anzufangen ist der sichere Weg.',
    'next-3' => 'Von da an handelt der Agent als Sie: er kann, was Sie können, und nichts darüber hinaus, und jeder seiner Aufrufe wird aufgeschrieben.',
    'mine' => 'Ihre Verbindungen',

];
