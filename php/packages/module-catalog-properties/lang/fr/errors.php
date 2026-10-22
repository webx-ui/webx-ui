<?php

declare(strict_types=1);

return [
    'type' => 'Une propriété est l’un de : :types.',
    'type-fixed' => 'Le type d’une propriété ne change plus une fois enregistré : créez une nouvelle propriété et déplacez-y les valeurs.',
    'code' => 'Lettres latines, chiffres et tirets simples, :max caractères au plus.',
    'code-taken' => 'Ce code est déjà pris dans cette langue par : :holder.',
    'slug' => 'Lettres latines, chiffres et tirets simples.',
    'slug-taken' => 'Une autre valeur de cette propriété a ce slug dans cette langue.',
    'color' => 'Une couleur s’écrit #rrggbb.',
    'group' => 'Ce groupe n’existe pas.',
    'group-in-use' => 'Le groupe contient des propriétés. Propriétés : :count',
    'unknown-property' => 'Cette propriété n’existe pas.',
    'inherited' => '« :property » est déjà dans l’ensemble de « :category » au-dessus ; une catégorie en hérite.',
    'outside-set' => 'Cette propriété n’est pas dans l’ensemble de la catégorie principale du produit.',
    'number' => 'Un nombre est attendu.',
    'text' => 'Un texte est attendu, ou une table de langues.',
    'one-value' => 'Cette propriété prend une seule valeur.',
    'unknown-value' => 'Cette valeur n’existe pas pour cette propriété.',
    'unknown-slug' => '« :property » n’a pas de valeur « :slug ». Créez-la d’abord avec catalog_property_values_create, ou indiquez l’id.',
    'leaves-only' => 'Seule une valeur sans rien en dessous peut être choisie ici.',
    'value-in-use' => 'Des produits ont cette valeur — fusionnez-la plutôt avec une autre. Produits : :count',
    'values-select' => 'Seul un référentiel a des valeurs.',
    'not-tree' => 'Les valeurs de cette propriété ne forment pas un arbre.',
    'move-into-itself' => 'Une valeur ne peut pas aller sous elle-même.',
    'merge-other' => 'Une valeur se fusionne avec une autre valeur de la même propriété.',
    'merge-descendant' => 'Une valeur ne peut pas être fusionnée avec une valeur en dessous d’elle.',
    'intervals-number' => 'Seul un nombre a des intervalles.',
    'interval-ends' => 'La borne supérieure doit être au-dessus de la borne inférieure.',
];
