<?php

declare(strict_types=1);

/*
 * The page that hands a person the address and walks them through their client. It is read
 * once, by somebody who has never done this before, and often out loud over a call.
 */

return [
    'lead' => 'Un agente de IA — Claude, ChatGPT, Cursor — puede trabajar en este panel en su nombre. Dele la dirección de abajo: le pedirá que inicie sesión y luego preguntará qué puede hacer.',
    'title' => 'Conectar un agente',
    'address' => 'La dirección de este panel para agentes',
    'copy' => 'Copiar la dirección',
    'copied' => 'Copiado',
    'copy-failed' => 'El portapapeles no está disponible. Seleccione la dirección y cópiela a mano.',
    'copy-line' => 'Copiar',
    'how' => 'Cómo añadirlo',
    'claude-title' => 'Claude',
    'claude-1' => 'Abra los ajustes y vaya a «Connectors».',
    'claude-2' => 'Pulse «Add custom connector».',
    'claude-3' => 'Pegue la dirección y pulse «Add».',
    'chatgpt-title' => 'ChatGPT',
    'chatgpt-1' => 'Abra los ajustes y vaya a «Connectors».',
    'chatgpt-2' => 'Pulse «Add» y elija un servidor MCP propio.',
    'chatgpt-3' => 'Pegue la dirección, deje OAuth como autenticación y confirme.',
    'claude-code-title' => 'Claude Code',
    'claude-code-hint' => 'Una línea en una terminal, desde cualquier proyecto:',
    'codex-title' => 'Codex',
    'codex-hint' => 'Estas líneas en ~/.codex/config.toml:',
    'one-click' => 'Con un clic',
    'one-click-hint' => 'Estos dos lo aceptan todo por un enlace: el editor se abre, pregunta una vez y el servidor está puesto.',
    'install-in' => 'Añadir a :client',
    'cursor-title' => 'Cursor',
    'vscode-title' => 'VS Code',
    'next' => 'Qué pasa después',
    'next-1' => 'El cliente abre este sitio en un navegador y le pide iniciar sesión en el panel con su contraseña de siempre. Nunca la ve.',
    'next-2' => 'El panel muestra quién lo pide y adónde va la respuesta. Ahí está el interruptor «solo lectura», y empezar con él es lo prudente.',
    'next-3' => 'A partir de ahí el agente actúa como usted: puede lo que usted puede y nada más, y cada llamada suya queda anotada.',
    'mine' => 'Sus conexiones',

];
