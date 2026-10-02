<?php

declare(strict_types=1);

return [
    'menu' => [
        'broken' => [
            'title' => 'Éléments de menu qui mènent à une erreur',
            'found' => 'Un élément de menu mène à une page qui répond par une erreur, ou à un enregistrement qui n’a plus d’adresse.',
            'why' => 'Un menu est sur chaque page : un élément cassé est un lien cassé partout, et la première chose sur laquelle le visiteur clique.',
            'fix' => 'Faites pointer l’élément vers une page qui existe, ou supprimez-le.',
        ],
        'redirect' => [
            'title' => 'Éléments de menu qui mènent à une redirection',
            'found' => 'Un élément de menu mène à une adresse qui redirige ailleurs.',
            'why' => 'Chaque clic coûte un aller-retour de plus, sur chaque page où le menu est affiché.',
            'fix' => 'Faites pointer l’élément vers l’adresse où il aboutit.',
        ],
    ],
];
