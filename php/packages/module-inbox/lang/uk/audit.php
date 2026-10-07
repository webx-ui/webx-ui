<?php

declare(strict_types=1);

return [
    'no-recipients' => 'Форма :form (:slug) нікого не сповіщає',
    'notify-failed' => 'Листів не пішло за останні :days дн.: :count',
    'notify-queued' => 'Листів чекають у черзі довше :minutes хв.: :count',
    'captcha-keys' => 'Форма :form (:slug) просить :provider, а в .env сайту немає :missing',
    'captcha-unused' => 'У форми :form (:slug) немає капчі, а в сайту є ключі для :providers',
    'spam-without-captcha' => 'Форма :form (:slug) без капчі отримує спам. За :days дн. позначено спамом: :spam, відхилено: :refused',
];
