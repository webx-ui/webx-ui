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
        'captcha_keys' => [
            'title' => 'Formulaires demandant un captcha sans clés sur le site',
            'found' => 'Un formulaire activé demande reCAPTCHA ou Turnstile, et il manque au .env du site la clé du site, le secret ou les deux.',
            'why' => 'Sans clé du site le widget n’est pas affiché ; sans secret aucune réponse ne peut être vérifiée. Dans les deux cas le formulaire refuse chaque envoi et les visiteurs ne peuvent pas vous joindre.',
            'fix' => 'Ajoutez WEBX_INBOX_RECAPTCHA_KEY et WEBX_INBOX_RECAPTCHA_SECRET (ou la paire TURNSTILE) au .env du site, avec WEBX_INBOX_RECAPTCHA_TYPE selon le type de clé, et videz le cache de configuration (php artisan config:clear). Ou désactivez le captcha dans l’onglet Antispam du formulaire.',
        ],
        'captcha_unused' => [
            'title' => 'Formulaires sans captcha sur un site qui a les clés',
            'found' => 'Un formulaire activé ne demande pas de captcha, alors que le site a des clés pour reCAPTCHA ou Turnstile.',
            'why' => 'Le champ caché, l’horodatage et la limite par adresse arrêtent la plupart des robots, ce n’est donc qu’une remarque : le captcha est prêt pour le jour où le formulaire recevra du spam.',
            'fix' => 'Si le formulaire reçoit du spam, activez le captcha dans son onglet Antispam. Sinon, ignorez ce problème.',
        ],
        'spam_without_captcha' => [
            'title' => 'Formulaires sans captcha qui reçoivent du spam',
            'found' => 'Un formulaire activé sans captcha a reçu ces derniers jours des envois marqués comme spam, ou l’antispam a refusé des envois.',
            'why' => 'Des robots ont trouvé le formulaire. Ce qui passe arrive dans la boîte et dans les notifications, et les couches gratuites sont justement ce qu’ils essaient de contourner.',
            'fix' => 'Activez un captcha dans l’onglet Antispam du formulaire — le site a besoin de ses clés dans le .env — et gardez le champ caché. Les refus sont comptés par jour dans le cache : un cache vidé repart de zéro.',
        ],
    ],
];
