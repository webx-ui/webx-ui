<?php

declare(strict_types=1);

return [
    'sign_in' => [
        'title' => 'Connexion',
        'remember' => 'Se souvenir de moi',
        'submit' => 'Se connecter',
        'no_account' => 'Pas encore de compte ?',
        'register' => 'S’inscrire',
    ],
    'register' => [
        'title' => 'Créer un compte',
        'submit' => 'S’inscrire',
        'password_hint' => 'Au moins :min caractères.',
        'have_account' => 'Vous avez déjà un compte ?',
        'sign_in' => 'Se connecter',
    ],
    'fields' => [
        'email' => 'E-mail',
        'password' => 'Mot de passe',
        'first_name' => 'Prénom',
        'last_name' => 'Nom',
        'phone' => 'Téléphone',
    ],
    'failed' => 'E-mail ou mot de passe incorrect.',
    'blocked' => 'Ce compte est bloqué. Veuillez contacter la boutique.',
    'taken' => 'Cette adresse est déjà enregistrée — connectez-vous ou réinitialisez votre mot de passe.',
    'value_taken' => 'Cette valeur est déjà utilisée.',
    'throttled' => 'Trop de tentatives. Veuillez patienter :seconds s.',
    'forgot' => [
        'link' => 'Mot de passe oublié ?',
        'title' => 'Restaurer le mot de passe',
        'intro' => 'Saisissez l’e-mail de votre compte : nous enverrons un lien pour un nouveau mot de passe.',
        'submit' => 'Envoyer le lien',
        'sent' => 'S’il existe un compte avec cette adresse, nous y avons envoyé un lien.',
        'back' => 'Retour à la connexion',
    ],
    'reset' => [
        'title' => 'Nouveau mot de passe',
        'password' => 'Nouveau mot de passe',
        'confirmation' => 'Répétez le mot de passe',
        'submit' => 'Enregistrer le mot de passe',
        'expired' => 'Le lien a expiré. Demandez-en un nouveau ci-dessous.',
    ],
    'verify' => [
        'banner' => 'Confirmez votre e-mail : nous avons envoyé un message à',
        'resend' => 'Renvoyer',
        'sent_short' => 'Envoyé',
        'close' => 'Fermer',
        'sent' => 'Nous avons renvoyé le message à :email.',
        'already' => 'Votre e-mail est déjà confirmé.',
        'throttled' => 'Le message a déjà été envoyé. Réessayez dans :minutes min.',
        'sign_in' => 'Se connecter',
        'continue' => 'Continuer',
        'confirmed' => [
            'title' => 'E-mail confirmé',
            'text' => 'Merci : votre adresse est confirmée.',
        ],
        'expired' => [
            'title' => 'Le lien a expiré',
            'text' => 'Ce lien ne fonctionne plus. Connectez-vous pour recevoir un nouveau message.',
        ],
        'unverified' => [
            'title' => 'Confirmez votre e-mail',
            'text' => 'Cette page s’ouvre une fois votre e-mail confirmé. Nous avons envoyé un message à :email.',
        ],
    ],
    'email' => [
        'new' => 'Nouvel e-mail',
        'password' => 'Mot de passe actuel',
        'wrong_password' => 'Le mot de passe est incorrect.',
        'same' => 'Vous vous connectez déjà avec cette adresse.',
        'taken' => 'Cette adresse appartient à un autre compte.',
        'sent' => 'Nous avons envoyé un lien à :email. L’adresse changera quand vous l’ouvrirez.',
        'changed' => [
            'title' => 'Adresse modifiée',
            'text' => 'Vous vous connectez désormais avec :email.',
        ],
    ],
    'social' => [
        'or' => 'ou',
        'continue' => 'Continuer avec :provider',
        'failed' => 'La connexion a échoué. Veuillez réessayer.',
        'expired' => 'Le délai pour terminer est écoulé. Reconnectez-vous avec le réseau.',
        'closed' => 'Aucun compte n’utilise ce profil :provider, et l’inscription est fermée.',
        'sign_in_to_link' => 'Un compte avec cette adresse existe déjà. Connectez-vous avec votre mot de passe — la connexion avec :provider sera associée.',
        'providers' => [
            'google' => 'Google',
            'facebook' => 'Facebook',
        ],
        'fields' => [
            'client_id' => 'ID client',
            'client_secret' => 'Secret client',
            'app_id' => 'ID de l’application',
            'app_secret' => 'Secret de l’application',
        ],
        'complete' => [
            'title' => 'Presque terminé',
            'text' => ':provider ne nous a pas tout transmis pour le compte. Complétez le reste.',
            'email_hint' => 'Nous enverrons un e-mail pour la confirmer.',
            'submit' => 'Créer le compte',
        ],
    ],
];
