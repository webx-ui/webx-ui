<?php

declare(strict_types=1);

return [
    'catalog' => [
        'category_description' => [
            'title' => 'Catégories sans description',
            'found' => 'Des catégories publiées n’ont pas de description.',
            'why' => 'Une page de catégorie qui ne contient qu’une liste de produits dit peu de chose à un moteur de recherche et passe facilement pour une page pauvre.',
            'fix' => 'Écrivez quelques phrases sur ce que contient la catégorie et sur la façon d’y choisir.',
        ],
        'product_image' => [
            'title' => 'Produits sans image',
            'found' => 'Des produits publiés n’ont pas d’image.',
            'why' => 'Un produit sans image se vend moins bien, paraît inachevé dans la liste et n’a rien à montrer quand on le partage.',
            'fix' => 'Ajoutez au moins une image à chaque produit, ou retirez du site ceux qui ne sont pas prêts.',
        ],
        'product_price' => [
            'title' => 'Produits sans prix',
            'found' => 'Des produits publiés n’ont pas de prix, ou un prix nul.',
            'why' => 'Le visiteur ne sait pas combien cela coûte, et le produit ne peut pas apparaître dans les résultats d’achat.',
            'fix' => 'Indiquez le prix, ou retirez du site les produits qui ne sont pas encore en vente.',
        ],
    ],
];
