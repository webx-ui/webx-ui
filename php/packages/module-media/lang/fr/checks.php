<?php

declare(strict_types=1);

return [
    'media' => [
        'missing_file' => [
            'title' => 'Fichiers de la bibliothèque absents du disque',
            'found' => 'La médiathèque liste un fichier que le disque n’a pas.',
            'why' => 'Chaque page et chaque champ qui l’utilisent affichent une image cassée ou un lien de téléchargement mort.',
            'fix' => 'Téléversez de nouveau le fichier dans la médiathèque, ou copiez le dossier storage depuis l’endroit d’où vient le site.',
        ],
        'heavy' => [
            'title' => 'Images trop lourdes pour une page',
            'found' => 'Des images de la médiathèque pèsent plus que la limite.',
            'why' => 'Une page qui en affiche une se charge lentement sur un téléphone, et les moteurs de recherche classent plus bas les pages lentes.',
            'fix' => 'Remplacez-les par des versions plus petites : une photo pour une page a rarement besoin de dépasser 2000 pixels de large ou quelques centaines de kilo-octets.',
        ],
    ],
];
