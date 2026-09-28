<?php

declare(strict_types=1);

// The regions of the layout (the regions spec): the words a declaration may point at with
// `trans::webx-blocks::regions.header`, and what the server says about a region.
return [
    'header' => 'Шапка',
    'footer' => 'Подвал',
    'usage' => 'Зона «:title»',
    'preview-failed' => 'Блок этой зоны падает. На сайте вместо всей зоны будет разметка из кода.',
    'not-in-registry' => ':path — не страница из реестра адресов сайта, поэтому зона показана на пустой странице лейаута.',
    'too-many' => 'В зоне может быть не больше :max блоков.',
    'not-allowed' => 'Блок «:type» нельзя поставить в эту зону.',
    'refused' => 'Зона не принимает эти блоки.',
    'conflict' => 'Зону изменили, пока она была открыта. Перезагрузите, чтобы увидеть изменения.',
    'failed-block' => 'Блок «:type» (:key) падает: :reason',
    'not-published' => 'Не опубликовано: блок черновика не рисуется.',
    'nothing-to-publish' => 'Зону ни разу не сохраняли — публиковать нечего.',
    'no-version' => 'У зоны нет версии :number.',
    'no-fallback' => 'Лейаут ещё не назвал вьюху для этой зоны, или её больше нет.',
    'adopt-taken' => 'Тип блока «:slug» уже есть.',
    'adopt-failed' => 'Разметку не удалось перенести в тип блока.',
    'adopt-forbidden' => 'Чтобы перенести разметку в тип блока, нужно право править типы блоков.',
];
