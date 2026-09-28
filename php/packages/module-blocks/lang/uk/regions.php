<?php

declare(strict_types=1);

// The regions of the layout (the regions spec): the words a declaration may point at with
// `trans::webx-blocks::regions.header`, and what the server says about a region.
return [
    'header' => 'Шапка',
    'footer' => 'Підвал',
    'usage' => 'Зона «:title»',
    'preview-failed' => 'Блок цієї зони падає. На сайті замість усієї зони буде розмітка з коду.',
    'not-in-registry' => ':path — не сторінка з реєстру адрес сайту, тому зону показано на порожній сторінці лейаута.',
    'too-many' => 'У зоні може бути не більше :max блоків.',
    'not-allowed' => 'Блок «:type» не можна поставити в цю зону.',
    'refused' => 'Зона не приймає ці блоки.',
    'conflict' => 'Зону змінили, поки вона була відкрита. Перезавантажте, щоб побачити зміни.',
    'failed-block' => 'Блок «:type» (:key) падає: :reason',
    'not-published' => 'Не опубліковано: блок чернетки не малюється.',
    'nothing-to-publish' => 'Зону жодного разу не зберігали — публікувати нічого.',
    'no-version' => 'У зони немає версії :number.',
    'no-fallback' => 'Лейаут ще не назвав в’юху для цієї зони, або її більше немає.',
    'adopt-taken' => 'Тип блоку «:slug» уже існує.',
    'adopt-failed' => 'Розмітку не вдалося перенести в тип блоку.',
    'adopt-forbidden' => 'Щоб перенести розмітку в тип блоку, потрібне право редагувати типи блоків.',
];
