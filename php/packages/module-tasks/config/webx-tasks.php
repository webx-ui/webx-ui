<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | Notifications (§6a)
    |---------------------------------------------------------------------------
    |
    | What a person is told about, by default — the panel's settings screen of
    | the section overrides every one of these. `stale_hours` is how long a
    | stage may stay in progress before it is reported as hung.
    |
    */

    'notify' => [
        'review_human' => true,
        'task_done' => true,
        'blocked' => true,
        'rework' => false,
        'review_auto' => false,
        'stale' => true,
        'stale_hours' => 6,
    ],

    /*
    |---------------------------------------------------------------------------
    | Telegram
    |---------------------------------------------------------------------------
    |
    | The bot's token and the chats are set on the settings screen; the token
    | is stored encrypted and never read back. `api` is here for a proxy, or
    | for a test that answers instead of Telegram.
    |
    */

    'telegram' => [
        'api' => env('WEBX_TASKS_TELEGRAM_API', 'https://api.telegram.org'),
        'timeout' => 10,
    ],

];
