<?php

declare(strict_types=1);

return [
    'currency' => 'Фид ведётся в валюте сайта: :currency.',
    'currency-unset' => 'У сайта не задана валюта: укажите WEBX_CATALOG_CURRENCY.',
    'filter-facet' => 'Фильтр фида ссылается на [:facet], которого на сайте нет.',
    'source-property' => 'Фид берёт MPN из свойства [:property], которого на сайте нет.',
    'no-item' => 'У фида нет шаблона товара.',
    'template' => 'Шаблон не читается: :reason',
    'too-large' => 'Файл больше допустимого размера фида.',
    'stale' => 'Сборка перестала двигаться и закрыта.',
    'file-xml' => 'Файл — не корректный XML, строка :line: :reason',
    'file-json' => 'Шапка и подвал не дают корректный JSON: :reason',
    'file-json-item' => 'Товар :product не даёт корректный JSON: :reason',
    'file-csv' => 'В строке :line файла полей :found вместо :expected.',
    'sample-json' => 'Проба — не корректный JSON, строка :line: :reason',
    'sample-place' => 'Часть «:part», строка :line: :fragment',
    'unknown-variable' => 'В части «:part» нет переменной {{:name}}.',
    'no-products' => 'Подходящих товаров пока нет: проверены только шапка и подвал.',
];
