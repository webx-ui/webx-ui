<?php

declare(strict_types=1);

return [
    'seo' => [
        'redirect_chain' => [
            'title' => 'Redirections qui mènent à des redirections',
            'found' => 'Une redirection de la table SEO pointe vers une adresse qui est elle-même redirigée.',
            'why' => 'Chaque saut est un aller-retour de plus pour le visiteur, et les moteurs de recherche cessent de suivre au bout de quelques-uns.',
            'fix' => 'Faites pointer la première redirection directement vers la dernière adresse — le bouton de correction le fait pour chaque redirection exacte de la chaîne.',
        ],
        'title_duplicate' => [
            'title' => 'Le même titre dans plusieurs fiches SEO',
            'found' => 'Plusieurs entités ont le même titre dans leur fiche SEO, dans la même langue.',
            'why' => 'Deux pages qui se présentent de la même façon se font concurrence dans la recherche, et aucune ne ressemble à la réponse.',
            'fix' => 'Donnez à chaque page un titre qui dit ce qu’elle contient et rien d’autre.',
        ],
        'redirect_broken' => [
            'title' => 'Redirections vers une page cassée',
            'found' => 'Une redirection exacte envoie les visiteurs vers une adresse qui a répondu par une erreur pendant l’exploration.',
            'why' => 'Le visiteur qui a suivi un ancien lien arrive sur une page d’erreur, et le poids de l’ancienne adresse est perdu.',
            'fix' => 'Faites pointer la redirection vers une page qui existe, ou rétablissez la page.',
        ],
        'rule_dead' => [
            'title' => 'Règles SEO pour des adresses disparues',
            'found' => 'Une règle SEO exacte est écrite pour une adresse qui a répondu 404 ou 410 pendant l’exploration.',
            'why' => 'Rien de nuisible, mais la règle ne sert à personne et masque le fait que la page pour laquelle elle a été écrite a disparu.',
            'fix' => 'Supprimez la règle, ou ajoutez une redirection depuis cette adresse si la page a été déplacée.',
        ],
    ],
];
