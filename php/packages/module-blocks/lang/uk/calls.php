<?php

declare(strict_types=1);

return [
    'kind' => 'Тип — це або блок, або компонент.',
    'kind-in-use' => 'Блок стоїть на сторінках і не може стати компонентом. Сторінок: :count.',
    'unknown-call' => '«:type» — не тип блоку: на сайті виклик нічого не надрукує.',
    'dynamic-call' => 'Тип, що викликається, не записаний буквально: його публікація цей шаблон не перевірить.',
    'delete-used-by' => 'Цей тип викликають інші, і в їхніх шаблонах лишиться порожнє місце. Спершу приберіть виклики.',
    'delete-used-by-one' => 'Викликає «:title» (:slug)',
    'publish-cycle' => 'Типи викликають одне одного по колу: :path.',
    'publish-breaks-parent' => 'Ламає «:parent» на його зразку: :reason',
    'publish-breaks-parent-on' => 'Ламає «:parent» на «:entity»: :reason',
    'publish-breaks-declared' => 'Ламає місце, звідки його викликає модуль :module: :reason',
    'customise-exists' => 'Тип із таким ідентифікатором уже є.',
    'customised-from' => 'З :view',
];
