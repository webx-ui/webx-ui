<?php

declare(strict_types=1);

// The journal of the exchange with gateways (spec §20): directions, kinds, signatures, outcomes.
return [
    'title' => 'Обмен со шлюзом',
    'directions' => [
        'out' => 'Шлюзу',
        'in' => 'От шлюза',
        'browser' => 'Через браузер покупателя',
        'internal' => 'Решение сайта',
    ],
    'kinds' => [
        'start' => 'Платёж открыт',
        'status' => 'Запрос статуса',
        'cancel' => 'Отмена',
        'complete' => 'Подтверждение одобрения',
        'capture' => 'Запрос списания',
        'void' => 'Запрос отмены холда',
        'connection' => 'Проверка подключения',
        'key' => 'Ключ подписи',
        'other' => 'Другой запрос',
        'notify' => 'Уведомление',
        'handoff' => 'Переход в шлюз',
        'return' => 'Возврат из шлюза',
        'expire' => 'Срок вышел',
        'manual' => 'Отмечено вручную',
        'captured' => 'Списано',
        'released' => 'Холд отпущен',
        'capture_refused' => 'Списание отклонено',
        'release_refused' => 'Отмена холда отклонена',
    ],
    'signature' => [
        'valid' => 'Подпись верна',
        'invalid' => 'Подпись неверна',
        'untrusted' => 'Без подписи — статус спрошен у шлюза',
        'none' => 'Не проверялась',
    ],
    'outcomes' => [
        'applied' => 'Применено',
        'same' => 'Без изменений',
        'ignored' => 'Пропущено',
        'duplicate' => 'Повтор',
        'rejected' => 'Отклонено',
        'unmatched' => 'Платёж не найден',
        'error' => 'Ошибка',
    ],
    'truncated' => 'Обрезано по лимиту',
    'download' => 'Скачать',
    'size' => 'Размер',
    'masked' => 'Секреты скрыты',
];
