<?php

declare(strict_types=1);

// The event `inbox.submission` of webx-ui/module-notifications: its name and the values its templates get.
return [
    'event' => 'Un envoi d’un formulaire',
    'vars' => [
        'form' => 'Le nom du formulaire',
        'form_slug' => 'Le slug du formulaire',
        'submission_id' => 'Le numéro de l’envoi',
        'submission_url' => 'L’envoi dans le panneau',
        'created_at' => 'Quand il est arrivé',
        'fields' => 'Toutes les réponses, une ligne chacune : « Libellé : valeur »',
        'answers' => 'Les réponses, sous forme de liste',
        'answer_name' => 'Le nom technique du champ',
        'answer_label' => 'Le libellé du champ',
        'answer_value' => 'La réponse',
        'field' => 'Une réponse, par le nom technique de son champ',
        'files' => 'Les noms des fichiers joints',
        'page' => 'La page d’où le formulaire a été envoyé',
        'locale' => 'La langue de l’envoi',
        'utm' => 'Balises UTM',
        'reply_to' => 'L’e-mail du visiteur, issu du champ e-mail du formulaire',
    ],
];
