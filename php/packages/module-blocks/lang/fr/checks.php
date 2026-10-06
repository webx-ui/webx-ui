<?php

declare(strict_types=1);

return [
    'no-marker' => 'Pas de data-wx-block sur la racine : le script ne s’exécutera pas, et le panneau ne pourra pas surligner le bloc dans l’aperçu.',
    'stray-selectors' => 'Sélecteurs hors du préfixe du bloc .b-:slug : :selectors',
    'bare-selectors' => 'Des sélecteurs d’élément touchent tout le site : :selectors',
    'media-query' => '@media mesure la fenêtre. Un bloc se dimensionne selon son conteneur : utilisez @container.',
    'variables-missing' => 'Le gabarit utilise :variables, que le schéma ne déclare pas. La publication sera refusée.',
    'ok-marker' => 'La racine porte data-wx-block.',
    'ok-prefix' => 'Chaque sélecteur commence par .b-:slug.',
    'ok-bare' => 'Aucun sélecteur d’élément nu.',
    'ok-container' => 'La largeur est décidée par des requêtes de conteneur.',
    'ok-variables' => 'Chaque variable du gabarit est un champ du schéma.',
    'blocks' => [
        'stray_values' => [
            'title' => 'Valeurs de bloc pour des champs absents du type',
            'found' => 'Des blocs gardent des valeurs de champs que leur type ne définit pas — restes d’un import ou d’un champ retiré du type.',
            'why' => 'Le visiteur ne les voit pas, mais elles sont dans les données de l’éditeur et dans ce que lit un agent, et ressortent comme un mauvais libellé de bloc.',
            'fix' => 'Retirez-les avec la correction, ou pour tout le site avec php artisan webx:blocks:prune. Un bloc d’un type qui n’existe plus n’est pas touché. Les éléments d’un répéteur sont comparés à ses champs.',
        ],
    ],
];
