<?php

declare(strict_types=1);

return [
    'columns' => [
        'id' => 'ID',
        'external_id' => 'Внешний ID',
    ],
    'errors' => [
        'forbidden' => 'Нужно право :permission.',
        'no-source' => 'Укажите файл: загрузку или адрес.',
        'upload-missing' => 'Нет такой законченной загрузки для обмена.',
        'not-a-url' => 'Это не адрес http(s): :url',
        'too-large' => 'Файл больше :max МБ.',
        'unreachable' => 'Файл не скачался: :reason',
        'unknown-format' => 'Обмен читает и пишет такие файлы: :known.',
        'option' => 'Настройка :name — одно из: :allowed.',
        'mapping-unknown' => 'Колонки :code нет или её вам нельзя записывать.',
        'mapping-twice' => 'Две колонки файла идут в :code.',
        'key-not-mapped' => 'Ключевая колонка :key должна быть среди колонок файла.',
        'profile-missing' => 'Нет такого профиля для этого направления.',
        'trashed' => 'Товар #:id в «Удалённых»: сначала восстановите его.',
        'not-an-id' => 'ID — целое положительное число.',
        'not-a-number' => 'Это не число.',
        'not-an-integer' => 'Это не целое число.',
        'not-a-boolean' => 'Да или нет: 1 или 0, yes или no, true или false.',
        'unknown-unit' => 'Такой единицы в каталоге нет. Есть: :known',
        'too-long' => 'Длиннее :max символов.',
        'external-id-taken' => 'Этот внешний ID уже у товара #:id.',
        'category-empty' => 'Путь категории пуст.',
        'category-unknown-id' => 'Категории #:id нет.',
        'category-missing' => 'Категории :path нет.',
        'category-ambiguous' => 'Здесь несколько категорий с названием :name: :ids. Назовите нужную по #id.',
        'create-forbidden' => 'Чтобы создать, нужно право :permission.',
    ],
];
