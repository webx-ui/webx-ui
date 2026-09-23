<?php

declare(strict_types=1);

/*
 * The words of the categories every module shares (`WebxUi\Admin\Categories`). A module that
 * calls its categories something else — rubrics — says its own refusal instead.
 */

return [
    'in-use' => 'В ней записей: :count. Сначала перенесите их в другую категорию.',
    'slug-shape' => 'Буквы, цифры и одиночные дефисы между ними.',

    // The shared screens of categories (`categories/` in @webx-ui/module-admin). A module
    // that calls its categories something else hands its own keys in over these.
    'new' => 'Новая категория',
    'empty' => 'Категорий пока нет.',
    'empty-help' => 'Категория объединяет записи. Одна запись может быть в нескольких.',
    'order' => 'Порядок здесь — это порядок на сайте',
    'hidden' => 'Скрыта на сайте',
    'no-address' => 'Нет адреса на этом языке',
    'count' => 'Записей: :count',
    'show-items' => 'Показать её записи',
    'edit' => 'Изменить',
    'open-on-site' => 'Открыть на сайте',
    'delete' => 'Удалить',
    'delete-blocked' => 'Пока в ней есть записи, удалить её нельзя — сначала перенесите их.',
    'delete-title' => 'Удалить «:name»?',
    'delete-text' => 'Она уйдёт в корзину и пропадёт с сайта, а её адрес освободится.',
    'deleted' => 'Категория в корзине.',
    'cancel' => 'Отмена',
    'create' => 'Создать',
    'save' => 'Сохранить',
    'saved' => 'Сохранено.',
    'save-failed' => 'Не сохранено — посмотрите на отмеченные поля.',
    'reorder-failed' => 'Новый порядок не сохранился.',
    'field-title' => 'Название',
    'field-slug' => 'Адрес',
    'address-moving' => 'Адрес меняется. Старый продолжит работать и будет вести на новый.',
    'untitled' => 'Без названия',
    'trail' => 'Где вы',
    'leave-title' => 'Уйти без сохранения?',
    'leave-text' => 'Всё, что изменено здесь после последнего сохранения, пропадёт.',
    'leave' => 'Уйти',
    'field-main' => 'Главная',
    'field-add' => 'Добавить категорию',
    'field-remove' => 'Убрать из этой категории',
    'field-empty' => 'Пока ни в одной категории.',
    'field-none-left' => 'Все категории уже выбраны.',
    'order-all' => 'Перетащите, чтобы поменять порядок на сайте.',
    'order-category' => 'Перетащите, чтобы поменять порядок внутри этой категории. У остального списка он свой.',
    'order-locked' => 'Чтобы поменять порядок, очистите поиск и фильтры: перетаскивать можно весь список или одну категорию.',
    'unknown' => 'Одной из выбранных категорий больше нет.',
];
