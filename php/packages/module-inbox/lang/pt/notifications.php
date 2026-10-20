<?php

declare(strict_types=1);

// The event `inbox.submission` of webx-ui/module-notifications: its name and the values its templates get.
return [
    'event' => 'Um envio de um formulário',
    // The default letter: a note that answering it writes to whoever filled the form.
    'reply' => 'Responda a este e-mail para responder ao visitante: :address.',
    'vars' => [
        'form' => 'O nome do formulário',
        'form_slug' => 'O slug do formulário',
        'submission_id' => 'O número do envio',
        'submission_url' => 'O envio no painel',
        'created_at' => 'Quando chegou',
        'fields' => 'Todas as respostas, uma por linha: «Rótulo: valor»',
        'answers' => 'As respostas, como lista',
        'answer_name' => 'O nome técnico do campo',
        'answer_label' => 'O rótulo do campo',
        'answer_value' => 'A resposta',
        'field' => 'Uma resposta, pelo nome técnico do seu campo',
        'files' => 'Os nomes dos ficheiros anexados',
        'page' => 'A página de onde o formulário foi enviado',
        'locale' => 'O idioma do envio',
        'utm' => 'Etiquetas UTM',
        'reply_to' => 'O e-mail do visitante, do campo de e-mail do formulário',
    ],
];
