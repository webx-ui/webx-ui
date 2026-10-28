<?php

declare(strict_types=1);

return [
    'menu' => [
        'label' => 'Mon compte',
        'profile' => 'Profil',
        'security' => 'Connexion et sécurité',
        'sign_out' => 'Se déconnecter',
    ],
    'profile' => [
        'title' => 'Profil',
        'personal' => 'Données personnelles',
        'save' => 'Enregistrer',
        'saved' => 'Enregistré.',
    ],
    'email' => [
        'title' => 'E-mail',
        'confirmed' => 'confirmé',
        'unconfirmed' => 'non confirmé',
        'pending' => 'En attente de confirmation : :email — suivez le lien de l’e-mail.',
        'submit' => 'Changer l’e-mail',
    ],
    'security' => [
        'title' => 'Connexion et sécurité',
    ],
    'password' => [
        'title' => 'Mot de passe',
        'current' => 'Mot de passe actuel',
        'new' => 'Nouveau mot de passe',
        'confirmation' => 'Répétez le nouveau mot de passe',
        'submit' => 'Changer le mot de passe',
        'changed' => 'Le mot de passe a été changé. Les autres appareils ont été déconnectés.',
        'none' => 'Votre compte n’a pas encore de mot de passe. Nous vous enverrons un lien pour en définir un.',
        'set' => 'Définir un mot de passe',
        'link_sent' => 'Nous avons envoyé un lien à :email.',
    ],
    'sessions' => [
        'title' => 'Se déconnecter partout',
        'text' => 'Met fin aux sessions sur tous les autres appareils et navigateurs. Vous restez connecté ici.',
        'submit' => 'Se déconnecter partout',
        'done' => 'Vous êtes déconnecté de tous les autres appareils.',
    ],
    'delete' => [
        'title' => 'Supprimer le compte',
        'text' => 'Le compte et tout ce qu’il contient sont supprimés définitivement. Ils ne peuvent pas être restaurés.',
        'password' => 'Mot de passe actuel',
        'email' => 'Votre e-mail',
        'email_hint' => 'Saisissez l’adresse du compte pour confirmer.',
        'wrong_email' => 'Ce n’est pas l’adresse de ce compte.',
        'submit' => 'Supprimer définitivement',
    ],
    'social' => [
        'title' => 'Réseaux sociaux',
        'link' => 'Associer',
        'unlink' => 'Dissocier',
        'linked_short' => 'Associé',
        'since' => 'depuis le :date',
        'linked' => 'La connexion avec :provider est associée.',
        'elsewhere' => 'Ce compte :provider est déjà associé à un autre profil.',
        'other' => 'Un autre compte :provider est déjà associé. Dissociez-le d’abord.',
        'last' => 'Définissez un mot de passe pour dissocier le dernier moyen de connexion.',
        'not_linked' => 'Ce réseau n’est pas associé.',
    ],
];
