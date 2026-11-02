<?php

declare(strict_types=1);

return [
    'title' => 'Livraison',
    'cache' => 'Modes de livraison et transporteurs',
    'cache_points' => 'Points de retrait des transporteurs',
    'carriers' => [
        'store' => 'La boutique',
    ],
    'point_kinds' => [
        'branch' => 'Agence',
        'locker' => 'Consigne automatique',
        'store' => 'Magasin',
        'pickup' => 'Point relais',
    ],
    'locality_kinds' => [
        'city' => 'Ville',
        'town' => 'Ville',
        'township' => 'Bourg',
        'village' => 'Village',
        'settlement' => 'Hameau',
        'other' => 'Localité',
    ],
    'step' => [
        'delivery' => 'Livraison',
        'locality' => 'Ville ou localité',
        'locality_placeholder' => 'Commencez à saisir le nom',
        'where' => 'Où livrer la commande ?',
        'choose_locality' => 'Choisissez une ville',
        'change' => 'Modifier',
        'popular' => 'Villes populaires',
        'show_methods' => 'Afficher les modes de livraison',
        'no_methods' => 'Aucun mode de livraison n’est disponible ici',
        'free' => 'Gratuit',
        'carrier_rates' => 'Selon les tarifs du transporteur',
        'choose_point' => 'Choisissez une agence',
        'pick_up_here' => 'Retirer ici',
        'open_until' => 'Ouvert aujourd’hui jusqu’à :time',
        'closed_today' => 'Fermé aujourd’hui',
        'street' => 'Rue',
        'house' => 'Numéro',
        'apartment' => 'Appartement',
        'note' => 'Note pour le livreur',
        'cost' => 'Frais de livraison',
        'points' => 'Points : :count',
    ],
    // The line of the cart's totals, and the benefit the shop's cart rules may grant (§9.4).
    'totals' => [
        'delivery' => 'Livraison',
    ],
    'benefits' => [
        'paid_by_store' => 'La boutique paie la livraison',
    ],
    'hints' => [
        'no_streets' => 'La livraison par coursier n’est pas disponible ici, choisissez un autre mode',
    ],
    // A street of a carrier's directory as shown: its name with the type word where the language puts it.
    'street_kinds' => [
        'street' => 'rue :name',
        'avenue' => 'avenue :name',
        'lane' => 'allée :name',
        'square' => 'place :name',
        'other' => ':name',
    ],
    'errors' => [
        'choose_point' => 'Choisissez un point de retrait.',
        'point_gone' => 'Ce point de retrait n’est plus disponible, choisissez-en un autre.',
        'choose_street' => 'Choisissez la rue dans la liste.',
        'street_required' => 'Indiquez la rue.',
        'house_required' => 'Indiquez le numéro.',
        'too_long' => ':max caractères au plus.',
        'no_quote' => 'Impossible de calculer la livraison, choisissez un autre mode.',
    ],
    'sync' => [
        'running' => 'Déjà en cours, page :page',
        'errors' => [
            'no_answer' => 'Le transporteur n\'a pas répondu',
            'key_rejected' => 'Le transporteur a refusé la clé d\'API',
            'bad_response' => 'La réponse du transporteur n\'avait pas le format attendu',
            'failed' => 'La synchronisation a échoué côté site — voir le journal',
            'interrupted' => 'La synchronisation a été interrompue',
        ],
    ],
    'schedule' => [
        'closed' => 'Fermé',
        'around_the_clock' => 'Ouvert 24 h/24',
        'until' => 'Jusqu\'à :time',
        'from' => 'Ouvre à :time',
    ],
];
