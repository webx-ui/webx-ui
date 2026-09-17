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
    'signed-out' => 'Oturumunuz kapandı. Yeniden girip tekrar deneyin.',
    'forbidden' => 'Bunu yapma yetkiniz yok.',
    'gone' => 'Artık burada değil — biri silmiş olabilir.',
    'conflict' => 'Siz üzerinde çalışırken biri bunu değiştirdi.',
    'throttled' => 'Çok fazla deneme. Birazdan tekrar deneyin.',
    'throttled-in' => 'Çok fazla deneme. :seconds saniye sonra tekrar deneyin.',
    'server' => 'Sunucu bunu yapamadı. Birazdan tekrar deneyin.',
    'offline' => 'Sunucu yanıt vermedi. Bağlantıyı kontrol edip tekrar deneyin.',
    'unknown' => 'Olmadı.',
];
