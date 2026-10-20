<?php

declare(strict_types=1);

// The event `inbox.submission` of webx-ui/module-notifications: its name and the values its templates get.
return [
    'event' => 'A submission of a form',
    // The default letter: a note that answering it writes to whoever filled the form.
    'reply' => 'Reply to this letter to answer the visitor: :address.',
    'vars' => [
        'form' => 'The name of the form',
        'form_slug' => 'The slug of the form',
        'submission_id' => 'The number of the submission',
        'submission_url' => 'The submission in the panel',
        'created_at' => 'When it arrived',
        'fields' => 'Every answer, a line each: “Label: value”',
        'answers' => 'The answers, as a list',
        'answer_name' => 'The machine name of the field',
        'answer_label' => 'The label of the field',
        'answer_value' => 'The answer',
        'field' => 'One answer, by the machine name of its field',
        'files' => 'The names of the attached files',
        'page' => 'The page the form was sent from',
        'locale' => 'The language of the submission',
        'utm' => 'UTM tags',
        'reply_to' => 'The visitor\'s e-mail, from the form\'s e-mail field',
    ],
];
