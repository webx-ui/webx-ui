<?php

declare(strict_types=1);

return [
    'home-exists' => 'Ce site a déjà une page d’accueil ; une seconde racine est impossible.',
    'home-immovable' => 'La page d’accueil ne peut pas être déplacée.',
    'home-undeletable' => 'La page d’accueil ne peut pas être supprimée.',
    'home-address' => 'La page d’accueil n’a pas d’adresse propre : son slug reste vide.',
    'home-missing' => 'Ce site n’a pas de page d’accueil, une nouvelle page n’a donc nulle part où aller. Lancez les migrations.',
    'home-no-siblings' => 'La page d’accueil n’a pas de voisines ; une page ne peut aller qu’à l’intérieur.',
    'move-into-self' => 'Une page ne peut pas être déplacée dans elle-même ni dans ses propres pages.',
    'parent-trashed' => 'Cette page est à la corbeille. Restaurez-la avant d’y placer quoi que ce soit.',
    'not-in-bin' => 'Seule une page de la corbeille peut être supprimée définitivement. Supprimez-la d’abord.',
    'slug-shape' => 'Une adresse accepte lettres, chiffres, tirets et tirets bas.',
    'ancestor-trashed' => 'La page au-dessus, « :title » (#:id), est dans la corbeille. Restaurez-la d’abord.',
    'move-beside-self' => 'Une page ne peut pas être placée avant ou après elle-même.',
    'title-required' => 'Le titre ne peut pas être vide dans la langue principale du site (:locale).',

    // The editor.
    'conflict' => ':name a modifié cette page pendant que vous la modifiiez.',
    'conflict-anonymous' => 'Cette page a changé pendant que vous la modifiiez.',
];
