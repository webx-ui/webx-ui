<?php

declare(strict_types=1);

return [
    'actions' => [
        'publish' => 'Опубликовать',
        'unpublish' => 'Снять',
        'set-category' => 'Задать основную категорию',
        'add-category' => 'Добавить категорию',
        'remove-category' => 'Убрать категорию',
        'delete' => 'Удалить',
        'restore' => 'Восстановить',
    ],
    'params' => [
        'category' => 'Категория',
    ],
    'errors' => [
        'selection' => 'Выберите товары: список id или запрос списка.',
        'too-many' => 'Слишком много id сразу — отправьте запрос списка.',
        'unknown-action' => 'Такого массового действия нет. Известные: :known',
        'forbidden' => 'Для этого действия нужно право :permission.',
        'gone' => 'Товара уже нет там, где его нашёл прогон.',
    ],
];
