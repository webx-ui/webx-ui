<?php

declare(strict_types=1);

// The journal of the exchange with gateways (spec §20): directions, kinds, signatures, outcomes.
return [
    'title' => 'Обмін зі шлюзом',
    'directions' => [
        'out' => 'Шлюзу',
        'in' => 'Від шлюзу',
        'browser' => 'Через браузер покупця',
        'internal' => 'Рішення сайту',
    ],
    'kinds' => [
        'start' => 'Платіж відкрито',
        'status' => 'Запит статусу',
        'cancel' => 'Скасування',
        'complete' => 'Підтвердження схвалення',
        'capture' => 'Запит списання',
        'void' => 'Запит скасування холду',
        'connection' => 'Перевірка підключення',
        'key' => 'Ключ підпису',
        'other' => 'Інший запит',
        'notify' => 'Сповіщення',
        'handoff' => 'Перехід до шлюзу',
        'return' => 'Повернення зі шлюзу',
        'expire' => 'Строк минув',
        'manual' => 'Позначено вручну',
        'captured' => 'Списано',
        'released' => 'Холд відпущено',
        'capture_refused' => 'Списання відхилено',
        'release_refused' => 'Скасування холду відхилено',
    ],
    'signature' => [
        'valid' => 'Підпис правильний',
        'invalid' => 'Підпис неправильний',
        'untrusted' => 'Без підпису — статус запитано у шлюзу',
        'none' => 'Не перевірявся',
    ],
    'outcomes' => [
        'applied' => 'Застосовано',
        'same' => 'Без змін',
        'ignored' => 'Пропущено',
        'duplicate' => 'Повтор',
        'rejected' => 'Відхилено',
        'unmatched' => 'Платіж не знайдено',
        'error' => 'Помилка',
    ],
    'truncated' => 'Обрізано за лімітом',
    'download' => 'Завантажити',
    'size' => 'Розмір',
    'masked' => 'Секрети приховано',
];
