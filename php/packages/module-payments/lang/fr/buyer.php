<?php

declare(strict_types=1);

// What the buyer reads (spec §10, §12, §17): the payment step, the bank details, the order's line.
return [
    'step' => [
        'methods' => 'Mode de paiement',
        'save' => 'Enregistrer',
        'delivery_first' => 'Choisissez d\'abord la livraison — les modes de paiement en dépendent',
        'method_dropped' => 'Le mode de paiement choisi n\'est pas disponible pour cette livraison. Choisissez-en un autre.',
    ],
    'errors' => [
        'choose_method' => 'Choisissez un mode de paiement',
        'method_gone' => 'Ce mode de paiement n\'est plus disponible, choisissez-en un autre',
    ],
    'instructions' => [
        'title' => 'Coordonnées bancaires pour le paiement',
        'recipient' => 'Bénéficiaire',
        'account' => 'Compte',
        'purpose' => 'Motif du paiement',
        'note' => 'Délai',
    ],
    'status' => [
        'paid' => 'Payée',
        'awaiting' => 'En attente de paiement',
        'pay' => 'Payer',
    ],
    'pay' => [
        'title' => 'Paiement de la commande :number',
        'description' => 'Commande :number',
        'due' => 'À payer : :amount',
        'paid' => 'La commande :number est payée',
        'cancelled' => 'La commande :number est annulée',
        'checking' => 'Nous vérifions le paiement. Cela prend généralement jusqu’à une minute.',
        'checking_long' => 'La banque n’a pas encore confirmé le paiement. Si l’argent a été débité, la commande sera marquée payée d’elle-même et nous enverrons un e-mail. Vous pouvez fermer cette page.',
        'check_again' => 'Vérifier à nouveau',
        'held' => 'Paiement accepté. Le montant est réservé sur votre carte et sera débité par la boutique.',
        'received' => 'Paiement reçu',
        'accepted' => 'Paiement accepté',
        'failed' => 'Le paiement n’a pas abouti',
        'unavailable' => 'Impossible d’ouvrir le paiement. Réessayez ou choisissez un autre moyen.',
        'on_receipt' => 'La commande se paie à la réception',
        'order_link' => 'Voir la commande',
        'redirecting' => 'Redirection vers la page de paiement…',
        'go' => 'Aller au paiement',
        'top_up' => 'Complément à payer : :amount',
        'top_up_button' => 'Payer le complément',
        'online_now' => 'Payer en ligne maintenant',
        'wait' => 'Si vous avez déjà payé dans un autre onglet, attendez une minute avant de payer à nouveau.',
    ],
];
