<?php

declare(strict_types=1);

return [
    'currency' => 'Фід ведеться у валюті сайту: :currency.',
    'currency-unset' => 'У сайту не задано валюту: вкажіть WEBX_CATALOG_CURRENCY.',
    'filter-facet' => 'Фільтр фіда посилається на [:facet], якого на сайті немає.',
    'source-property' => 'Фід бере MPN із властивості [:property], якої на сайті немає.',
    'no-item' => 'У фіда немає шаблону товару.',
    'template' => 'Шаблон не читається: :reason',
    'too-large' => 'Файл більший за допустимий розмір фіда.',
    'stale' => 'Збірка перестала рухатися й закрита.',
    'file-xml' => 'Файл — некоректний XML, рядок :line: :reason',
    'file-json' => 'Шапка й підвал не дають коректний JSON: :reason',
    'file-json-item' => 'Товар :product не дає коректний JSON: :reason',
    'file-csv' => 'У рядку :line файлу полів :found замість :expected.',
];
