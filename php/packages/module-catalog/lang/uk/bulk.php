<?php

declare(strict_types=1);

return [
    'actions' => [
        'publish' => 'Опублікувати',
        'unpublish' => 'Зняти',
        'set-category' => 'Задати основну категорію',
        'add-category' => 'Додати категорію',
        'remove-category' => 'Прибрати категорію',
        'delete' => 'Видалити',
        'restore' => 'Відновити',
    ],
    'params' => [
        'category' => 'Категорія',
    ],
    'errors' => [
        'selection' => 'Виберіть товари: список id або запит списку.',
        'too-many' => 'Забагато id за раз — надішліть запит списку.',
        'unknown-action' => 'Такої масової дії немає. Відомі: :known',
        'forbidden' => 'Для цієї дії потрібне право :permission.',
        'gone' => 'Товару вже немає там, де його знайшов прогін.',
    ],
];
