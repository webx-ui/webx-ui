<?php

declare(strict_types=1);

return [
    'inbox' => [
        'no_recipients' => [
            'title' => 'Formulaires qui ne préviennent personne',
            'found' => 'Un formulaire activé ne nomme aucun destinataire qu’un e-mail atteindrait : aucun, seulement des administrateurs supprimés ou désactivés depuis, ou des adresses qui n’en sont pas.',
            'why' => 'Chaque envoi est enregistré et le visiteur remercié, mais personne ne le sait avant d’ouvrir la boîte de réception — une demande peut attendre des jours.',
            'fix' => 'Ouvrez le formulaire, allez dans l’onglet Notifications et ajoutez un administrateur ou une adresse. Si le formulaire n’est lu que dans le panneau, ignorez ce constat.',
        ],
        'notification' => [
            'title' => 'Notifications de soumissions qui ne sont pas parties',
            'found' => 'Des lettres sur des soumissions ont échoué ou attendent longtemps dans la file.',
            'why' => 'Les personnes désignées par le formulaire ignorent des demandes que le panneau contient déjà, et le site ne le dit pas.',
            'fix' => 'Échouées : corrigez les réglages de messagerie, redémarrez le worker de la file (php artisan queue:restart) pour qu’il les relise, puis renvoyez la notification depuis la soumission. En attente : démarrez un worker de la file.',
        ],
    ],
];
