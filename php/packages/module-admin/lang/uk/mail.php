<?php

declare(strict_types=1);

/*
 * Where the site's letters go, as the panel, the doctor and the agent say it
 * (`WebxUi\Admin\Mail\MailRoutes`).
 */

return [
    'trap-transport' => 'Листи йдуть у відправник «:transport»: там вони записуються, і ніхто їх не отримує.',
    'trap-host' => 'Листи йдуть на :host — це тестова скринька: поза нею їх ніхто не отримує.',
    'stale-config' => 'Налаштування в .env змінено після того, як конфіг було закешовано, і вони ще не застосовані. Перезберіть кеш: php artisan webx:boot.',
    'queue-delay' => 'Листи йдуть через чергу: зазвичай протягом хвилини, коли черга відпрацює.',
    'queue-stalled' => 'У черзі довше за :minutes хв. Черга працює?',
    'trapped' => 'Прийнято тестовим транспортом',
];
