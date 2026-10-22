<?php

declare(strict_types=1);

return [
    'title' => 'Nom',
    'code' => 'Code',
    'code-help' => 'Lettres latines, chiffres et tirets : l’état dans l’adresse du filtre (stock_in-stock) et dans les modèles. Créé à partir du nom s’il reste vide.',
    'color' => 'Teinte',
    'color-help' => 'Le site colore la ligne d’état dans cette teinte ; le panneau affiche une étiquette de la même teinte.',
    'tones' => [
        'neutral' => 'Neutre',
        'primary' => 'Principale',
        'success' => 'Succès',
        'warning' => 'Avertissement',
        'danger' => 'Danger',
        'info' => 'Info',
    ],
    'purchasable' => 'Peut être acheté',
    'purchasable-help' => 'Désactivé, un produit dans cet état ne peut pas être acheté, et son nom est ce que le site affiche à la place du bouton d’achat.',
    'default' => 'Par défaut',
    'default-help' => 'L’état de tout produit pour lequel personne n’a choisi d’état, et d’un nouveau produit. Pour le changer, faites d’un autre état l’état par défaut.',
    'filterable' => 'Dans le filtre',
    'filterable-help' => 'Désactivé, l’état n’est pas un choix du filtre du catalogue.',
    'new' => 'Nouvel état',
    'empty' => 'Aucun état de stock pour l’instant.',
    'empty-help' => 'Un état indique si un produit est en stock et s’il peut être acheté.',
    'order' => 'L’ordre ici est celui du filtre',
    'hidden' => 'Pas dans le filtre',
    'count' => 'Produits : :count',
    'show-items' => 'Afficher ses produits',
    'delete-blocked' => 'Tant que des produits l’ont, il ne peut pas partir — passez-les d’abord à un autre état.',
    'delete-text' => 'Il part à la corbeille et quitte le filtre.',
    'deleted' => 'L’état est à la corbeille.',
    'saved' => 'L’état est enregistré.',
];
