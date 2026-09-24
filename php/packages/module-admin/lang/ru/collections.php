<?php

declare(strict_types=1);

/*
 * What a block shows from another section (`wx-collection`): the refusals of the server, then the
 * words of the field that makes the choice.
 */

return [
    'unknown-source' => 'На этом сайте нет раздела «:source», из которого можно показывать записи.',
    'unknown-category' => 'Одной из выбранных категорий больше нет.',
    'limit' => 'Сколько показывать: целое число от 1 до :max или пусто — все.',
    'flag' => 'Этот переключатель принимает только «да» или «нет».',
    'unknown-relation' => 'Записи этого раздела нельзя отбирать по такой связи.',
    'unknown-related' => 'Одной из выбранных связанных записей больше нет.',

    'field-source' => 'Показывает записи из раздела «:source».',
    'field-unavailable' => 'Записи из «:source» здесь выбрать нельзя: раздел не установлен или у вас нет к нему доступа.',
    'field-categories' => 'Категории',
    'field-all' => 'Все категории',
    'field-no-categories' => 'Такой категории нет.',
    'field-limit' => 'Сколько показывать',
    'field-limit-all' => 'Все',
    'field-filter' => 'Фильтр по категориям над списком',
    'field-markup' => 'Разметка для поисковиков',
    'field-markup-auto-on' => 'По умолчанию включена: блок показывает все категории.',
    'field-markup-auto-off' => 'По умолчанию выключена: поисковики просят не размечать одни и те же записи на нескольких страницах, а выбранная категория обычно стоит на нескольких.',
    'field-markup-reset' => 'Вернуть как по умолчанию',
];
