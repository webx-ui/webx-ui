<?php

declare(strict_types=1);

return [
    'no-recipients' => 'Форма :form (:slug) никого не уведомляет',
    'notify-failed' => 'Писем не ушло за последние :days дн.: :count',
    'notify-queued' => 'Писем ждут в очереди дольше :minutes мин.: :count',
    'captcha-keys' => 'Форма :form (:slug) просит :provider, а в .env сайта нет :missing',
    'captcha-unused' => 'У формы :form (:slug) нет капчи, а у сайта есть ключи для :providers',
    'spam-without-captcha' => 'Форма :form (:slug) без капчи получает спам. За :days дн. отмечено спамом: :spam, отклонено: :refused',
];
