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
];
