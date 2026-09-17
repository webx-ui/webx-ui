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
    'signed-out' => 'You are signed out. Sign in again and try once more.',
    'forbidden' => 'You are not allowed to do that.',
    'gone' => 'It is not there any more — somebody may have deleted it.',
    'conflict' => 'Somebody changed this while you were working on it.',
    'throttled' => 'Too many attempts. Try again in a moment.',
    'throttled-in' => 'Too many attempts. Try again in :seconds seconds.',
    'server' => 'The server could not do that. Try again in a moment.',
    'offline' => 'The server did not answer. Check the connection and try again.',
    'unknown' => 'That did not work.',
];
