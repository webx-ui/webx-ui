<?php

declare(strict_types=1);

// The event `inbox.submission` of webx-ui/module-notifications: its name and the values its templates get.
return [
    'event' => 'Заявка из формы',
    'vars' => [
        'form' => 'Название формы',
        'form_slug' => 'Слаг формы',
        'submission_id' => 'Номер заявки',
        'submission_url' => 'Заявка в панели',
        'created_at' => 'Когда пришла',
        'fields' => 'Все ответы, по строке на каждый: «Подпись: значение»',
        'answers' => 'Ответы списком',
        'answer_name' => 'Машинное имя поля',
        'answer_label' => 'Подпись поля',
        'answer_value' => 'Ответ',
        'field' => 'Один ответ, по машинному имени поля',
        'files' => 'Имена приложенных файлов',
        'page' => 'Страница, с которой отправили форму',
        'locale' => 'Язык заявки',
        'utm' => 'UTM-метки',
        'reply_to' => 'E-mail посетителя из e-mail-поля формы',
    ],
];
