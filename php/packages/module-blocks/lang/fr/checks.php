<?php

declare(strict_types=1);

return [
    'no-marker' => 'Pas de data-wx-block sur la racine : le script ne s’exécutera pas, et le panneau ne pourra pas surligner le bloc dans l’aperçu.',
    'stray-selectors' => 'Sélecteurs hors du préfixe du bloc .b-:slug : :selectors',
    'bare-selectors' => 'Des sélecteurs d’élément touchent tout le site : :selectors',
    'media-query' => '@media mesure la fenêtre. Un bloc se dimensionne selon son conteneur : utilisez @container.',
    'string-on-text' => 'Avec un shortcode dedans, :field est du HTML : une fonction de chaîne ou un cast passe ce HTML à {{ }}, qui l’échappe une seconde fois. Modifiez-le via wx_text() : {{ wx_text(:field)->trimEnd(".") }} — trim, trimStart, stripPrefix, stripSuffix et map() le gardent en HTML.',
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
        'unknown_shortcodes' => [
            'title' => 'Shortcodes mal saisis',
            'found' => 'Un texte contient un crochet qui est presque un shortcode du site, ou qui a des arguments, sans en être un.',
            'why' => 'Seuls les shortcodes enregistrés sont remplacés. Le reste est imprimé tel quel, crochets compris, sous les yeux de chaque visiteur.',
            'fix' => 'Corrigez le nom selon la suggestion, ou écrivez [[nom]] si la page doit montrer les crochets. La liste est dans l’aide des shortcodes du champ et sous « Réglages » → « Shortcodes ».',
        ],
        'hardcoded_values' => [
            'title' => 'Valeurs au lieu d’un shortcode',
            'found' => 'Un texte contient un téléphone, un e-mail ou une autre valeur qu’un shortcode de « Réglages » → « Shortcodes » tient déjà.',
            'why' => 'Juste aujourd’hui, faux le jour où la valeur change : le shortcode change partout, une valeur saisie à la main seulement là où l’on y pense.',
            'fix' => 'Remplacez la valeur par le shortcode suggéré, p. ex. [phone]. Le lien est créé par le shortcode lui-même.',
        ],
    ],
    'syntax' => 'Le modèle ne compile pas : :reason. La publication sera refusée.',
    'unknown-field-type' => 'Champs d\'un type que le site ne connaît pas : :fields. Le formulaire affiche un avertissement à leur place et rien ne vérifie leurs valeurs.',
    'field-id' => 'Un identifiant de champ est fait de lettres, chiffres, _ et -, et commence par une lettre : :ids.',
    'marker-slug' => 'La racine est marquée data-wx-block=":marker", mais l’identifiant est «:slug» : le script et le panneau trouvent le bloc par l’identifiant exact. La publication sera refusée.',
];
