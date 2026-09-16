<?php

declare(strict_types=1);

return [
    'title' => 'nom',
    'parent_id' => 'dossier parent',
    'directory_id' => 'dossier',
    'files' => 'fichiers',
    'item' => 'Fichier :number : :message',
    'shape' => "Ce n'est pas un fichier de la bibliothèque.",
    'accept' => "Ce champ n'accepte que :kind.",
    'localized' => 'Un champ de fichiers ne se traduit pas — les légendes qu’il contient, si.',
    'kind' => [
        'image' => 'des images',
        'video' => 'des vidéos',
        'audio' => 'des fichiers audio',
        'document' => 'des documents',
        'other' => "d'autres fichiers",
    ],
];
