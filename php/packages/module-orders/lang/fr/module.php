<?php

declare(strict_types=1);

return [
    'orders' => 'Commandes',
    'description' => 'Les commandes passées sur le site : ce qui a été acheté, par qui, et où en est chacune.',
    'empty' => 'Aucune commande pour l’instant.',
    'empty-help' => 'Une commande apparaît ici dès qu’elle est passée sur le site.',
    'order' => 'Commande',
    'statuses-cache' => 'Statuts des commandes',
    'fields' => [
        'customer_first_name' => 'Prénom',
        'customer_last_name' => 'Nom',
        'customer_phone' => 'Téléphone',
        'customer_email' => 'E-mail',
        'recipient' => 'Destinataire',
        'comment' => 'Commentaire de l\'acheteur',
        'manager_note' => 'Note du gestionnaire',
        'user_id' => 'Visiteur',
    ],
    'milestones' => [
        'placed' => 'Passée',
        'awaiting_payment' => 'En attente de paiement',
        'paid' => 'Payée',
        'received' => 'Reçue',
        'cancelled' => 'Annulée',
        'refunded' => 'Remboursée',
    ],
    'colors' => [
        'neutral' => 'Neutre',
        'info' => 'Info',
        'success' => 'Succès',
        'warning' => 'Avertissement',
        'danger' => 'Danger',
        'accent' => 'Accent',
    ],
];
