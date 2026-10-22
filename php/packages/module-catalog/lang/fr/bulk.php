<?php

declare(strict_types=1);

return [
    'actions' => [
        'publish' => 'Publier',
        'unpublish' => 'Retirer du site',
        'set-category' => 'Définir la catégorie principale',
        'add-category' => 'Ajouter une catégorie',
        'remove-category' => 'Retirer une catégorie',
        'delete' => 'Supprimer',
        'restore' => 'Restaurer',
    ],
    'params' => [
        'category' => 'Catégorie',
    ],
    'errors' => [
        'selection' => 'Choisissez les produits : une liste d’ID ou la requête de la liste.',
        'too-many' => 'Trop d’ID à la fois ; envoyez plutôt la requête de la liste.',
        'unknown-action' => 'Cette action groupée n’existe pas. Connues : :known',
        'forbidden' => 'Cette action exige le droit :permission.',
        'gone' => 'Le produit n’est plus là où l’exécution l’a trouvé.',
    ],
];
