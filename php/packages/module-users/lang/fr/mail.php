<?php

declare(strict_types=1);

return [
    'hello' => 'Bonjour, :name !',
    'ignore' => 'Si vous ne l’avez pas demandé, ignorez simplement ce message.',
    'verify' => [
        'subject' => 'Confirmez votre e-mail sur :site',
        'text' => 'Confirmez qu’il s’agit bien de votre adresse sur :site.',
        'button' => 'Confirmer l’e-mail',
        'expires' => 'Le lien est valable :hours heures.',
    ],
    'reset' => [
        'subject' => 'Un nouveau mot de passe sur :site',
        'text' => 'Quelqu’un a demandé à restaurer le mot de passe de votre compte sur :site.',
        'button' => 'Définir un nouveau mot de passe',
        'expires' => 'Le lien est valable :minutes minutes et une seule fois.',
    ],
    'password-changed' => [
        'subject' => 'Votre mot de passe sur :site a été modifié',
        'text' => 'Le mot de passe de votre compte sur :site vient d’être modifié.',
        'not-you' => 'Si ce n’était pas vous, restaurez le mot de passe immédiatement.',
        'button' => 'Restaurer le mot de passe',
    ],
    'email-change' => [
        'subject' => 'Confirmez la nouvelle adresse sur :site',
        'text' => 'Ouvrez le lien pour vous connecter désormais à :site avec :email.',
        'button' => 'Confirmer l’adresse',
        'expires' => 'Le lien est valable :minutes minutes.',
    ],
    'email-changed' => [
        'subject' => 'Votre adresse de connexion sur :site a été modifiée',
        'text' => 'Votre compte sur :site se connecte désormais avec :email.',
        'not-you' => 'Si ce n’était pas vous, restaurez le mot de passe et contactez-nous.',
        'button' => 'Restaurer le mot de passe',
    ],
];
