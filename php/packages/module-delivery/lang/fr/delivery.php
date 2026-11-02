<?php

declare(strict_types=1);

return [
    'title' => 'Livraison',
    'cache' => 'Modes de livraison et transporteurs',
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
    'hints' => [
        'no_streets' => 'La livraison par coursier n’est pas disponible ici, choisissez un autre mode',
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
];
