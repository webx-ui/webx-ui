<?php

declare(strict_types=1);

/*
 * The page that hands a person the address and walks them through their client. It is read
 * once, by somebody who has never done this before, and often out loud over a call.
 */

return [
    'lead' => 'Un agent IA — Claude, ChatGPT, Cursor — peut travailler dans ce panneau en votre nom. Donnez-lui l\'adresse ci-dessous : il vous demandera de vous connecter, puis ce qu\'il a le droit de faire.',
    'title' => 'Connecter un agent',
    'address' => 'Adresse de ce panneau pour les agents',
    'copy' => 'Copier l\'adresse',
    'copied' => 'Copié',
    'copy-failed' => 'Le presse-papiers est inaccessible. Sélectionnez l\'adresse et copiez-la à la main.',
    'copy-line' => 'Copier',
    'how' => 'Comment l’ajouter',
    'claude-title' => 'Claude',
    'claude-1' => 'Ouvrez les réglages et allez dans « Connectors ».',
    'claude-2' => 'Appuyez sur « Add custom connector ».',
    'claude-3' => 'Collez l\'adresse et appuyez sur « Add ».',
    'chatgpt-title' => 'ChatGPT',
    'chatgpt-1' => 'Ouvrez les réglages et allez dans « Connectors ».',
    'chatgpt-2' => 'Appuyez sur « Add » et choisissez un serveur MCP personnalisé.',
    'chatgpt-3' => 'Collez l\'adresse, laissez OAuth comme authentification et confirmez.',
    'claude-code-title' => 'Claude Code',
    'claude-code-hint' => 'Une ligne dans un terminal, depuis n\'importe quel projet :',
    'codex-title' => 'Codex',
    'codex-hint' => 'Ces lignes dans ~/.codex/config.toml :',
    'one-click' => 'En un clic',
    'one-click-hint' => 'Ces deux-là prennent tout par un lien : l\'éditeur s\'ouvre, demande une fois, et le serveur est là.',
    'install-in' => 'Ajouter à :client',
    'cursor-title' => 'Cursor',
    'vscode-title' => 'VS Code',
    'next' => 'Ce qui se passe ensuite',
    'next-1' => 'Le client ouvre ce site dans un navigateur et vous demande de vous connecter au panneau, avec votre mot de passe habituel. Il ne le voit jamais.',
    'next-2' => 'Le panneau montre qui demande et où part la réponse. Il y a là un interrupteur « lecture seule », et c\'est la façon prudente de commencer.',
    'next-3' => 'Ensuite l\'agent agit en votre nom : il peut ce que vous pouvez et rien de plus, et chacun de ses appels est consigné.',
    'mine' => 'Vos connexions',

];
