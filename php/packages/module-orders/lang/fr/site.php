<?php

declare(strict_types=1);

return [
    'marks' => [
        'paid' => 'Payée',
        'received' => 'Reçue',
        'cancelled' => 'Annulée',
        'refunded' => 'Remboursé :amount',
    ],
    'blocks' => [
        'delivery' => 'Livraison',
        'payment' => 'Paiement',
    ],
    'order' => [
        'title' => 'Commande :number',
        'placed' => 'Passée le :date',
        'scale' => 'Où en est la commande',
        'awaiting_payment' => 'La commande attend le paiement.',
        'items' => 'Articles',
        'subtotal' => 'Articles',
        'total' => 'Total',
        'tenders' => 'Payé avec le solde et des cartes cadeaux',
        'payable' => 'À payer',
        'contacts' => 'Contacts',
        'customer' => 'Acheteur',
        'recipient' => 'Destinataire',
        'comment' => 'Commentaire',
    ],
    'account' => [
        'menu' => 'Mes commandes',
        'title' => 'Mes commandes',
        'empty' => 'Aucune commande pour l’instant. Les commandes passées une fois connecté apparaîtront ici.',
        'back' => '← Toutes les commandes',
        'lines' => 'Articles : :count',
        'pages' => 'Pages',
        'newer' => '← Plus récentes',
        'older' => 'Plus anciennes →',
        'deletion' => 'Les commandes en cours (:numbers) seront honorées, mais vous ne les verrez plus dans votre compte.',
    ],
];
