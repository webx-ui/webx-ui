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
        'runner_silent' => true,
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

    /*
    |---------------------------------------------------------------------------
    | The watcher (§14)
    |---------------------------------------------------------------------------
    |
    | What the watcher that starts agent sessions is told, by default — the
    | settings screen of the section overrides the limits, the pause and the
    | runners. `silent_minutes` is how long it may go without a heartbeat
    | before it is reported as silent.
    |
    | `permissions` and `scopes` are added to the role `runner` and to the
    | watcher's own key, `session_scopes` to the key of one stage — how a
    | hosting module lets the watcher ask for admission and a worker read its
    | stand. `webx:tasks:runner` makes the role, the account and the key.
    |
    */

    'runner' => [
        'paused' => false,
        'max_workers' => 2,
        'per_project' => 1,
        'daily_sessions' => 40,
        'max_attempts' => 3,
        'runners' => [
            'claude' => ['enabled' => true, 'args' => ''],
            'agy' => ['enabled' => false, 'model' => '', 'args' => ''],
        ],
        'silent_minutes' => 5,
        'email' => 'taskd@runner.invalid',
        'permissions' => [],
        'scopes' => [],
        'session_scopes' => ['worker' => [], 'reviewer' => []],
        'session_days' => 2,
    ],

];
