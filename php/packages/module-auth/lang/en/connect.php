<?php

declare(strict_types=1);

/*
 * The page that hands a person the address and walks them through their client. It is read
 * once, by somebody who has never done this before, and often out loud over a call.
 */

return [
    'lead' => 'An AI agent — Claude, ChatGPT, Cursor — can work in this panel as you. Give it the address below; it will ask you to sign in and then ask what it may do.',
    'title' => 'Connect an agent',
    'address' => 'The address of this panel for agents',
    'copy' => 'Copy the address',
    'copied' => 'Copied',
    'copy-failed' => 'Could not reach the clipboard. Select the address and copy it by hand.',
    'copy-line' => 'Copy',
    'how' => 'How to add it',
    'claude-title' => 'Claude',
    'claude-1' => 'Open Settings and go to Connectors.',
    'claude-2' => 'Press "Add custom connector".',
    'claude-3' => 'Paste the address and press Add.',
    'chatgpt-title' => 'ChatGPT',
    'chatgpt-1' => 'Open Settings and go to Connectors.',
    'chatgpt-2' => 'Press "Add" and choose a custom MCP server.',
    'chatgpt-3' => 'Paste the address, leave the authentication as OAuth, and confirm.',
    'claude-code-title' => 'Claude Code',
    'claude-code-hint' => 'One line in a terminal, from any project:',
    'codex-title' => 'Codex',
    'codex-hint' => 'These lines in ~/.codex/config.toml:',
    'one-click' => 'One click',
    'one-click-hint' => 'These two take the whole thing from a link: the editor opens, asks once, and the server is there.',
    'install-in' => 'Add to :client',
    'cursor-title' => 'Cursor',
    'vscode-title' => 'VS Code',
    'next' => 'What happens next',
    'next-1' => 'The client opens this site in a browser and asks you to sign in to the panel, with your usual password. It never sees the password.',
    'next-2' => 'The panel shows who is asking and where the answer goes. There is a "read only" switch on that screen, and turning it on is the safe way to begin.',
    'next-3' => 'From then on the agent acts as you: it can do what you can do and nothing more, and every call it makes is written down.',
    'mine' => 'Your connections',

];
