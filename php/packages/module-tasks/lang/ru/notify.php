<?php

declare(strict_types=1);

return [
    'review-human' => 'Этап :position/:count «:stage» ждёт вашей проверки (попытка :attempt)',
    'review-auto' => 'Этап :position/:count «:stage» ждёт проверки агентом (попытка :attempt)',
    'blocked' => 'Агент на этапе :position/:count «:stage» спрашивает:',
    'rework' => 'Этап :position/:count «:stage» возвращён на доработку (попытка :attempt)',
    'stale' => 'Этап :position/:count «:stage» слишком долго в работе — :agent, часов: :hours',
    'done' => 'Задача выполнена. Принято этапов: :count',
    'result' => 'Результат: :result',
    'test' => 'Тестовое сообщение с :site: сюда будут приходить уведомления очереди задач.',
];
