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
    'signed-out' => 'Вы вышли из панели. Войдите снова и повторите.',
    'forbidden' => 'На это у вас нет прав.',
    'gone' => 'Этого больше нет — кто-то мог удалить.',
    'conflict' => 'Кто-то изменил это, пока вы работали.',
    'throttled' => 'Слишком много попыток. Повторите через минуту.',
    'throttled-in' => 'Слишком много попыток. Повторите через :seconds с.',
    'server' => 'Сервер не смог это сделать. Повторите через минуту.',
    'offline' => 'Сервер не ответил. Проверьте связь и повторите.',
    'unknown' => 'Не получилось.',
];
