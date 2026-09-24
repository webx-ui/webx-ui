<?php

declare(strict_types=1);

/*
 * A field that points at records of another section (`wx-relations`), and the same choice as a
 * block's filter ("only related to" in `wx-collection`). The keys are the panel's: the English
 * here is what `messages.ts` carries.
 */

return [
    'field-add' => 'Добавить…',
    'field-searching' => 'Ищем…',
    'field-nothing' => 'Ничего не нашлось.',
    'field-empty' => 'Пока ничего не выбрано.',
    'field-remove' => 'Убрать',
    'field-drag' => 'Перетащите, чтобы изменить порядок',
    'field-hidden' => 'Не на сайте',
    'field-trashed' => 'В корзине',
    'field-missing' => 'Не найдено',
    'field-full' => 'Можно выбрать не больше :max.',
    'field-forbidden' => 'Эти записи вам не видны, поэтому выбор здесь не изменить.',
    'collection-related' => 'Только связанные с',
    'collection-related-to' => 'Только связанные с «:target»',
    'collection-related-type' => 'Какой раздел',
    'collection-related-any' => 'Не сужено: все записи, связанные и нет.',
    'collection-related-current' => 'Записью страницы, на которой стоит блок',
    'collection-related-current-hint' => 'На странице записи из «:target» блок покажет то, что с ней связано; на любой другой странице — ничего.',
];
