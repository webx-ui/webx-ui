<?php

declare(strict_types=1);

/*
 * The words of the categories every module shares (`WebxUi\Admin\Categories`). A module that
 * calls its categories something else — rubrics — says its own refusal instead.
 */

return [
    'in-use' => 'У ній записів: :count. Спочатку перенесіть їх до іншої категорії.',
    'slug-shape' => 'Літери, цифри й поодинокі дефіси між ними.',

    // The shared screens of categories (`categories/` in @webx-ui/module-admin). A module
    // that calls its categories something else hands its own keys in over these.
    'new' => 'Нова категорія',
    'empty' => 'Категорій поки немає.',
    'empty-help' => 'Категорія об’єднує записи. Один запис може бути в кількох.',
    'order' => 'Порядок тут — це порядок на сайті',
    'hidden' => 'Прихована на сайті',
    'no-address' => 'Немає адреси цією мовою',
    'count' => 'Записів: :count',
    'show-items' => 'Показати її записи',
    'edit' => 'Змінити',
    'open-on-site' => 'Відкрити на сайті',
    'delete' => 'Видалити',
    'delete-blocked' => 'Поки в ній є записи, видалити її не можна — спершу перенесіть їх.',
    'delete-title' => 'Видалити «:name»?',
    'delete-text' => 'Вона піде в кошик і зникне з сайту, а її адреса звільниться.',
    'deleted' => 'Категорія в кошику.',
    'cancel' => 'Скасувати',
    'create' => 'Створити',
    'save' => 'Зберегти',
    'saved' => 'Збережено.',
    'save-failed' => 'Не збережено — подивіться на позначені поля.',
    'reorder-failed' => 'Новий порядок не зберігся.',
    'field-title' => 'Назва',
    'field-slug' => 'Адреса',
    'address-moving' => 'Адреса змінюється. Стара продовжить працювати й вестиме на нову.',
    'untitled' => 'Без назви',
    'trail' => 'Де ви',
    'leave-title' => 'Піти без збереження?',
    'leave-text' => 'Усе, що змінено тут після останнього збереження, зникне.',
    'leave' => 'Піти',
    'field-main' => 'Головна',
    'field-add' => 'Додати категорію',
    'field-remove' => 'Прибрати з цієї категорії',
    'field-empty' => 'Поки в жодній категорії.',
    'field-none-left' => 'Усі категорії вже вибрано.',
    'order-all' => 'Перетягніть, щоб змінити порядок на сайті.',
    'order-category' => 'Перетягніть, щоб змінити порядок усередині цієї категорії. В решти списку він свій.',
    'order-locked' => 'Щоб змінити порядок, очистьте пошук і фільтри: перетягувати можна весь список або одну категорію.',
    'unknown' => 'Однієї з вибраних категорій більше немає.',
];
