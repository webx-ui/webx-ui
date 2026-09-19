<?php

declare(strict_types=1);

return [
    'new' => 'Новый тег',
    'new-title' => 'Новый тег',
    'field-title' => 'Название',
    'field-slug' => 'Адрес',
    'field-slug-help' => 'Если оставить пустым, он получится из названия.',
    'create' => 'Создать',
    'cancel' => 'Отмена',

    'search' => 'Поиск по слову или адресу',
    'empty' => 'Тегов пока нет.',
    'no-address' => 'В этом языке адреса нет',

    'column-title' => 'Название',
    'column-address' => 'Адрес',
    'column-articles' => 'Статей',
    'column-indexing' => 'Индексация',

    'view-all' => 'Все',
    'view-empty' => 'Без статей',
    'view-noindex' => 'Не индексируются',
    'sort-articles' => 'По числу статей',
    'sort-name' => 'По алфавиту',

    'indexing-open' => 'в индексе',
    'indexing-rule' => 'в индексе — правило SEO',
    'indexing-noindex' => 'noindex',
    'indexing-rule-help' => 'На этот адрес есть правило в разделе SEO, поэтому страница в индексе, что бы ни стояло у тега.',

    'rename' => 'Переименовать',
    'renamed' => 'Тег переименован.',
    'index' => 'Индексировать',
    'noindex' => 'Убрать из индекса',
    'indexed' => 'Страница тега в индексе.',
    'kept-out' => 'Страница тега вне индекса.',
    'indexed-many' => 'Страниц тегов в индексе: :count.',
    'kept-out-many' => 'Страниц тегов убрано из индекса: :count.',
    'open-on-site' => 'Открыть на сайте',
    'show-articles' => 'Показать его статьи',

    'delete' => 'Удалить',
    'delete-title' => 'Удалить «:title»?',
    'delete-text' => 'У тега нет корзины: он исчезнет насовсем.',
    'delete-text-used' => 'Сейчас он стоит у статей: :count — и снимется со всех. У тега нет корзины: он исчезнет насовсем.',
    'deleted' => 'Тег удалён.',
    'delete-many-title' => 'Удалить тегов: :count?',
    'delete-many-text' => 'У тегов нет корзины: они исчезнут насовсем.',
    'delete-many-text-used' => 'Вместе они стоят у статей: :count — и снимутся со всех. Корзины у тегов нет.',
    'deleted-many' => 'Удалено тегов: :count.',

    'selected' => 'Выбрано: :count',
    'clear' => 'Снять выделение',
    'merge' => 'Слить в один',
    'merged' => 'Статей с тегом «:tag»: :count.',
    'merge-title' => 'Слить теги',
    'merge-keep' => 'Какой тег оставить',
    'merge-redirect' => 'Поставить редиректы со старых адресов',
    'merge-redirect-help' => ':addresses будут отвечать 301.',
    'merge-redirect-none' => 'У этих тегов нет адреса в этом языке, так что перенаправлять нечего.',
    'merge-warning' => 'Тег заменится на «:tag» у статей: :count. Тегов будет удалено: :merged — отменить это будет нечем.',
    'merge-confirm' => 'Слить',
];
