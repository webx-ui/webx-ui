<?php

declare(strict_types=1);

return [
    'audit' => [
        'replace-host' => [
            'title' => 'Remplacer l’environnement de développement par ce site',
            'description' => 'Chaque adresse de l’environnement de développement dans ce champ devient une adresse de ce site. L’enregistrement est sauvegardé par son propre module : la modification figure dans l’historique et peut être annulée.',
        ],
    ],
];
