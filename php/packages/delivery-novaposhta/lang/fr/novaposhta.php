<?php

declare(strict_types=1);

return [
    'carrier' => [
        'title' => 'Nova Poshta',
    ],
    'types' => [
        'branch' => [
            'title' => 'Agence Nova Poshta',
            'description' => 'Retrait dans une agence du transporteur ; vous payez la livraison au transporteur au retrait.',
        ],
        'locker' => [
            'title' => 'Consigne Nova Poshta',
            'description' => 'Retrait dans une consigne du transporteur ; vous payez la livraison au transporteur au retrait.',
        ],
        'courier' => [
            'title' => 'Coursier Nova Poshta',
            'description' => 'Le transporteur livre le colis à votre porte ; vous payez la livraison au transporteur à la réception.',
        ],
    ],
    'settings' => [
        'payment_method' => 'Paiement de la livraison',
        'payment_methods' => [
            'Cash' => 'Espèces',
            'NonCash' => 'Virement (sous contrat avec le transporteur)',
        ],
        'cargo_type' => 'Type de fret par défaut',
        'cargo_types' => [
            'Parcel' => 'Colis',
            'Cargo' => 'Fret',
            'Documents' => 'Documents',
        ],
        'weight' => 'Poids par défaut, kg',
        'description' => 'Description du contenu par défaut',
        'description_default' => 'Marchandises',
        'sender' => 'Expéditeur',
        'contact' => 'Personne de contact',
        'sender_city' => 'Ville d\'expédition',
        'sender_point' => 'Agence d\'expédition',
        'locker_width' => 'Consigne : largeur par défaut, cm',
        'locker_height' => 'Consigne : hauteur par défaut, cm',
        'locker_length' => 'Consigne : longueur par défaut, cm',
    ],
    'credentials' => [
        'api_key' => 'Clé API',
    ],
    'connection' => [
        'ok' => 'Expéditeurs dans le compte : :count',
        'key_refused' => 'Le transporteur n\'a pas accepté la clé API.',
        'no_answer' => 'Le transporteur n\'a pas répondu.',
        'no_sender' => 'Le compte du transporteur n\'a aucun expéditeur.',
        'no_key' => 'Saisissez d\'abord la clé API.',
    ],
    'waybill' => [
        'weight' => 'Poids, kg',
        'seats' => 'Colis',
        'cargo_type' => 'Type de fret',
        'description' => 'Description du contenu',
        'date' => 'Date d\'expédition',
        'width' => 'Largeur, cm',
        'height' => 'Hauteur, cm',
        'length' => 'Longueur, cm',
    ],
    'errors' => [
        'no_cod' => 'Le contre-remboursement n\'est pas encore pris en charge par ce transporteur.',
        'name_empty' => 'Saisissez le prénom et le nom du destinataire.',
        'name_letters' => 'Le transporteur n\'accepte le nom qu\'en lettres ukrainiennes.',
        'phone' => 'Le transporteur accepte un numéro mobile ukrainien : +380 et neuf chiffres.',
        'no_sender' => 'Choisissez d\'abord dans les réglages du transporteur l\'expéditeur, la personne de contact, la ville et l\'agence d\'expédition.',
        'contact_gone' => 'La personne de contact des réglages n\'est plus dans le compte du transporteur : choisissez-en une autre.',
        'no_town' => 'Le transporteur ne connaît pas la ville de la commande : associez-la dans la file des villes.',
        'no_point' => 'La commande n\'a ni agence ni consigne du transporteur.',
        'no_street' => 'La commande n\'a pas de rue du transporteur ou pas de numéro de maison.',
        'weight' => 'Le poids doit être supérieur à zéro.',
        'weight_too_much' => 'Le poids dépasse ce que le point accepte.',
        'seats' => 'Le nombre de colis est un entier à partir de un.',
        'locker_seats' => 'Une consigne accepte un seul colis.',
        'cargo_type' => 'Choisissez un type de fret dans la liste.',
        'size' => 'Les dimensions doivent être supérieures à zéro.',
        'locker_size' => 'Une consigne accepte jusqu\'à 40 × 30 × 60 cm.',
        'date' => 'La date d\'expédition est aujourd\'hui ou un jour dans les trois mois.',
        'read_only' => 'Ce site utilise la clé du transporteur en lecture seule : les lettres de voiture sont désactivées.',
        'no_ref' => 'L\'identifiant de cette lettre de voiture chez le transporteur n\'est pas conservé : annulez-la dans le compte du transporteur.',
        'carrier_said' => 'Le transporteur a refusé : :text',
    ],
    'remarks' => [
        'novaposhta_service_point' => 'Ignorés, points de service du transporteur : :count',
        'novaposhta_deny_to_select' => 'Ignorés, fermés au choix : :count',
        'novaposhta_invalid_point' => 'Ignorés, données incomplètes : :count',
    ],
];
