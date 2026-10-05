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
    ],
];
