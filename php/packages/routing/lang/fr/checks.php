<?php

declare(strict_types=1);

return [
    'routing' => [
        'orphan' => [
            'title' => 'Adresses d’enregistrements supprimés',
            'found' => 'Une ligne du registre des adresses pointe vers un enregistrement qui n’existe plus.',
            'why' => 'L’adresse répond 404 tout en gardant son nom, de sorte qu’un nouvel enregistrement ne peut pas le prendre.',
            'fix' => 'Lancez php artisan webx:routes:rebuild, ou rétablissez l’enregistrement s’il a été supprimé par erreur.',
        ],
        'alias_broken' => [
            'title' => 'Anciennes adresses qui ne mènent nulle part',
            'found' => 'Un alias — l’ancienne adresse conservée après le changement d’un slug — ne mène à aucune adresse, ou mène à un autre alias.',
            'why' => 'Un visiteur avec un ancien lien obtient un 404, ou une redirection vers une redirection.',
            'fix' => 'Lancez php artisan webx:routes:rebuild, ou supprimez l’alias dans l’onglet « Automatiques » de la section SEO.',
        ],
        'shadowed' => [
            'title' => 'Adresses auxquelles l’application répond elle-même',
            'found' => 'Une route de l’application a la même adresse qu’un enregistrement du registre.',
            'why' => 'L’enregistrement n’est jamais affiché : la route de l’application répond la première.',
            'fix' => 'Changez le slug de l’enregistrement, ou la route de l’application.',
        ],
        'no_address' => [
            'title' => 'Enregistrements sans adresse',
            'found' => 'Un enregistrement qui devrait avoir une adresse dans une langue n’en a pas.',
            'why' => 'La page ne peut pas être ouverte, n’est pas dans le plan du site et ne peut pas être liée.',
            'fix' => 'Enregistrez de nouveau l’enregistrement, ou lancez php artisan webx:routes:rebuild.',
        ],
        'unknown_type' => [
            'title' => 'Adresses d’un module non installé',
            'found' => 'Le registre contient des adresses d’un type qu’aucun module installé ne connaît.',
            'why' => 'Elles ne répondent à rien et gardent pourtant leurs noms face à tout nouvel enregistrement.',
            'fix' => 'Supprimez ces lignes, ou réinstallez le module.',
        ],
    ],
];
