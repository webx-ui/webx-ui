<?php

declare(strict_types=1);

return [
    'kind' => 'Тип — это либо блок, либо компонент.',
    'kind-in-use' => 'Блок стоит на страницах и не может стать компонентом. Страниц: :count.',
    'unknown-call' => '«:type» — не тип блока: на сайте вызов ничего не напечатает.',
    'dynamic-call' => 'Вызываемый тип не записан буквально: его публикация этот шаблон не проверит.',
    'delete-used-by' => 'Этот тип вызывают другие, и в их шаблонах останется пустое место. Сначала уберите вызовы.',
    'delete-used-by-one' => 'Вызывает «:title» (:slug)',
    'publish-cycle' => 'Типы вызывают друг друга по кругу: :path.',
    'publish-breaks-parent' => 'Ломает «:parent» на его образце: :reason',
    'publish-breaks-parent-on' => 'Ломает «:parent» на «:entity»: :reason',
    'publish-breaks-declared' => 'Ломает место, откуда его вызывает модуль :module: :reason',
    'customise-exists' => 'Тип с таким идентификатором уже есть.',
    'customised-from' => 'Из :view',
];
