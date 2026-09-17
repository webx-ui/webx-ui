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
    'signed-out' => 'Su sesión ha terminado. Vuelva a entrar e inténtelo otra vez.',
    'forbidden' => 'No tiene permiso para hacer eso.',
    'gone' => 'Ya no está ahí: alguien puede haberlo borrado.',
    'conflict' => 'Alguien lo cambió mientras usted trabajaba.',
    'throttled' => 'Demasiados intentos. Inténtelo dentro de un momento.',
    'throttled-in' => 'Demasiados intentos. Inténtelo dentro de :seconds segundos.',
    'server' => 'El servidor no ha podido hacerlo. Inténtelo dentro de un momento.',
    'offline' => 'El servidor no ha respondido. Compruebe la conexión e inténtelo otra vez.',
    'unknown' => 'No ha funcionado.',
];
