<?php

declare(strict_types=1);

return [
    'title' => 'Notifications',
    'channel' => 'Channel',
    'copy-name' => ':name (copy)',
    'unsaved' => 'Unsaved channel',
    'permissions' => [
        'view' => 'See the channels and the delivery log, with what visitors sent through forms.',
        'manage' => 'Set up channels and their secrets, send tests, find chats and send failed notifications again.',
    ],
    'statuses' => [
        'queued' => 'Queued',
        'sent' => 'Sent',
        'trapped' => 'Test transport',
        'failed' => 'Failed',
        'skipped' => 'Skipped',
    ],
    'reasons' => [
        'channel_deleted' => 'The channel is deleted',
        'channel_disabled' => 'The channel is switched off',
        'wrong_event' => 'The channel is for another event',
        'provider_missing' => 'The provider is not installed',
        'no_recipients' => 'The channel has no recipients',
        'no_address' => 'The event brought no address',
        'admin_deleted' => 'The administrator is deleted',
        'admin_inactive' => 'The administrator is switched off',
    ],
];
