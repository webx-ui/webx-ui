<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | Sending
    |---------------------------------------------------------------------------
    |
    | `mode` decides how a notification leaves the site:
    |
    |   auto           — through the queue, three attempts with a pause; on a
    |                    `sync` queue after the response instead, so the
    |                    visitor never waits for Telegram;
    |   queue          — always through the queue;
    |   after_response — always after the response, three attempts with short
    |                    pauses in one go: a site that has a queue but keeps
    |                    no worker.
    |
    | `connection` and `name` are the site's own queue when left empty.
    |
    */

    'queue' => [
        'mode' => env('WEBX_NOTIFICATIONS_QUEUE_MODE', 'auto'),
        'connection' => env('WEBX_NOTIFICATIONS_QUEUE_CONNECTION'),
        'name' => env('WEBX_NOTIFICATIONS_QUEUE'),
    ],

    /*
    |---------------------------------------------------------------------------
    | Delivery log
    |---------------------------------------------------------------------------
    |
    | Rows older than `days` are removed by webx:notifications:prune, which the
    | package puts on the schedule at `prune_at`. 0 keeps them forever. The log
    | holds what visitors typed into forms, so it is not kept for nothing.
    |
    */

    'log' => [
        'days' => (int) env('WEBX_NOTIFICATIONS_LOG_DAYS', 90),
        'prune_at' => '03:50',
    ],

    /*
    |---------------------------------------------------------------------------
    | Tests from the panel
    |---------------------------------------------------------------------------
    |
    | How many test messages one administrator may send in a minute: the button
    | writes to real people, and it should not become a way to flood them.
    |
    */

    'tests_per_minute' => 10,

    /*
    |---------------------------------------------------------------------------
    | Telegram
    |---------------------------------------------------------------------------
    |
    | The Bot API's address — another one only for a proxy or a local server —
    | and how long to wait for it, in seconds.
    |
    */

    'telegram' => [
        'api' => env('WEBX_NOTIFICATIONS_TELEGRAM_API', 'https://api.telegram.org'),
        'timeout' => 10,
    ],

];
