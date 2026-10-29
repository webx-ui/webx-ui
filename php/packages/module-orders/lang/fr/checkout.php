<?php

declare(strict_types=1);

/*
 * The checkout (spec §33). The words of the page, its steps and the «Thanks» come with their
 * stages; the tab «Checkout» of the settings is here now.
 */
return [
    'settings' => [
        'tab' => 'Commande',
        'fields' => 'Coordonnées de l\'acheteur',
        'fields-help' => 'Le prénom est toujours demandé. Une commande a besoin d\'un téléphone ou d\'un e-mail : au moins l\'un des deux reste obligatoire.',
        'last-name' => 'Nom',
        'phone' => 'Téléphone',
        'email' => 'E-mail',
        'recipient' => '« Le destinataire est une autre personne »',
        'recipient-help' => 'Une case sous les coordonnées pour une commande que quelqu\'un d\'autre retire.',
        'comment' => 'Commentaire',
        'comment-help' => 'Un champ où l\'acheteur laisse un commentaire sur la commande.',
        'terms-card' => 'Conditions',
        'terms' => 'Acceptation des conditions',
        'terms-help' => 'Mention — une ligne sous le bouton ; case — l\'acheteur la coche avant de commander. L\'acceptation est inscrite dans l\'historique de la commande.',
        'terms-page' => 'Page des conditions',
        'terms-page-help' => 'Une page du site ou une adresse vers laquelle renvoie l\'acceptation.',
        'privacy-page' => 'Politique de confidentialité',
        'privacy-page-help' => 'Un second document, si le site en a un : le traitement des données personnelles.',
        'contact-needed' => 'Gardez le téléphone ou l\'e-mail obligatoire : une commande a besoin de l\'un des deux.',
        'values' => [
            'required' => 'Obligatoire',
            'optional' => 'Facultatif',
            'hidden' => 'Masqué',
            'on' => 'Oui',
            'off' => 'Non',
            'none' => 'Aucune',
            'notice' => 'Mention',
            'checkbox' => 'Case',
        ],
    ],
];
