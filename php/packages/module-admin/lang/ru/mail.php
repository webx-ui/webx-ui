<?php

declare(strict_types=1);

/*
 * Where the site's letters go, as the panel, the doctor and the agent say it
 * (`WebxUi\Admin\Mail\MailRoutes`).
 */

return [
    'trap-transport' => 'Письма уходят в отправщик «:transport»: там они записываются, и никто их не получает.',
    'trap-host' => 'Письма уходят на :host — это тестовый ящик: за его пределами их никто не получает.',
    'stale-config' => 'Настройки в .env изменены после того, как конфиг был закеширован, и ещё не применены. Пересоберите кеш: php artisan webx:boot.',
    'queue-delay' => 'Письма уходят через очередь: обычно в течение минуты, когда очередь отработает.',
    'queue-stalled' => 'В очереди дольше :minutes мин. Очередь работает?',
    'trapped' => 'Принято тестовым транспортом',
];
