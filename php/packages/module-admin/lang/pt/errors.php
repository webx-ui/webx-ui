<?php

declare(strict_types=1);

/*
 * How a request fails, said in the panel's words.
 *
 * The browser half decides which status gets which line (`errors.ts`): a 422 is a refusal a
 * module wrote to be read, and everything else — a 404, a 403, a server that fell over — carries
 * text nobody wrote for an editor. These are what it says instead.
 */

return [
    'signed-out' => 'A sua sessão terminou. Entre de novo e tente outra vez.',
    'forbidden' => 'Não tem permissão para isso.',
    'gone' => 'Já não está aqui — alguém pode tê-lo apagado.',
    'conflict' => 'Alguém alterou isto enquanto trabalhava.',
    'throttled' => 'Demasiadas tentativas. Tente daqui a pouco.',
    'throttled-in' => 'Demasiadas tentativas. Tente daqui a :seconds segundos.',
    'server' => 'O servidor não conseguiu fazer isso. Tente daqui a pouco.',
    'offline' => 'O servidor não respondeu. Verifique a ligação e tente outra vez.',
    'unknown' => 'Não resultou.',
];
