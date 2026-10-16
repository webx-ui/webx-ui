<?php

declare(strict_types=1);

// The event `inbox.submission` of webx-ui/module-notifications: its name and the values its templates get.
return [
    'event' => 'Заявка з форми',
    'vars' => [
        'form' => 'Назва форми',
        'form_slug' => 'Слаг форми',
        'submission_id' => 'Номер заявки',
        'submission_url' => 'Заявка в панелі',
        'created_at' => 'Коли надійшла',
        'fields' => 'Усі відповіді, по рядку на кожну: «Підпис: значення»',
        'answers' => 'Відповіді списком',
        'answer_name' => 'Машинне ім’я поля',
        'answer_label' => 'Підпис поля',
        'answer_value' => 'Відповідь',
        'field' => 'Одна відповідь, за машинним ім’ям поля',
        'files' => 'Імена доданих файлів',
        'page' => 'Сторінка, з якої надіслали форму',
        'locale' => 'Мова заявки',
        'utm' => 'UTM-мітки',
        'reply_to' => 'E-mail відвідувача з e-mail-поля форми',
    ],
];
