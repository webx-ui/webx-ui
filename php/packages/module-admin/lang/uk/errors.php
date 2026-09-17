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
    'signed-out' => 'Ви вийшли з панелі. Увійдіть знову та повторіть.',
    'forbidden' => 'На це у вас немає прав.',
    'gone' => 'Цього більше немає — хтось міг видалити.',
    'conflict' => 'Хтось змінив це, поки ви працювали.',
    'throttled' => 'Забагато спроб. Повторіть за хвилину.',
    'throttled-in' => 'Забагато спроб. Повторіть за :seconds с.',
    'server' => 'Сервер не зміг цього зробити. Повторіть за хвилину.',
    'offline' => 'Сервер не відповів. Перевірте зв’язок і повторіть.',
    'unknown' => 'Не вийшло.',
];
