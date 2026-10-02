<?php

declare(strict_types=1);

return [
    'config' => [
        'debug' => [
            'title' => 'Mode débogage sur un domaine en production',
            'found' => 'APP_DEBUG=true sur un domaine qui n’est pas un environnement de développement.',
            'why' => 'Chaque page d’erreur montre le code, les requêtes et l’environnement, mots de passe compris, à quiconque en trouve une.',
            'fix' => 'Définissez APP_DEBUG=false dans .env et lancez php artisan config:cache.',
        ],
        'env' => [
            'title' => 'L’environnement n’est pas production',
            'found' => 'APP_ENV n’est pas production sur un domaine en service.',
            'why' => 'Hors production, les paquets se comportent différemment : caches, pages d’erreur, e-mails et outils de débogage.',
            'fix' => 'Définissez APP_ENV=production dans .env et lancez php artisan config:cache.',
        ],
        'app_url' => [
            'title' => 'APP_URL ne correspond pas au site',
            'found' => 'APP_URL diffère du schéma et de l’hôte sur lesquels répond le site.',
            'why' => 'Toutes les adresses absolues que le site imprime — le sitemap, les liens canoniques, les e-mails, les liens de fichiers — pointent ailleurs.',
            'fix' => 'Définissez APP_URL sur l’adresse que les visiteurs utilisent, avec https si le site l’a, et lancez php artisan config:cache.',
        ],
        'queue' => [
            'title' => 'La file s’exécute dans la requête',
            'found' => 'Le pilote de file est sync.',
            'why' => 'Les e-mails et les envois sont traités pendant que le visiteur attend, un serveur de messagerie lent ralentit les formulaires, et les tâches longues comme l’audit ne peuvent pas être lancées depuis le panneau.',
            'fix' => 'Utilisez la file database ou redis et gardez un worker actif (php artisan queue:work sous un superviseur).',
        ],
        'mail' => [
            'title' => 'Les e-mails ne vont nulle part',
            'found' => 'Le mailer écrit les e-mails dans le journal ou en mémoire.',
            'why' => 'Chaque formulaire dit « envoyé » et personne ne reçoit jamais d’e-mail.',
            'fix' => 'Configurez un vrai mailer (SMTP ou une API) dans .env : MAIL_MAILER et ses réglages.',
        ],
        'schedule' => [
            'title' => 'Le planificateur ne s’exécute pas',
            'found' => 'Le planificateur ne s’est pas exécuté depuis plus d’une heure.',
            'why' => 'Les sauvegardes, le nettoyage du journal et tout le reste du planning s’arrêtent en silence.',
            'fix' => 'Ajoutez « * * * * * php artisan schedule:run » au crontab de l’utilisateur du site.',
        ],
        'storage_link' => [
            'title' => 'Pas de lien public/storage',
            'found' => 'public/storage n’existe pas.',
            'why' => 'Chaque image et chaque fichier envoyé sur le site répond 404.',
            'fix' => 'Lancez php artisan storage:link sur le serveur.',
        ],
        'site_gate' => [
            'title' => 'Le site est protégé par un mot de passe',
            'found' => 'La barrière du site est activée.',
            'why' => 'Les moteurs de recherche ne voient rien derrière le mot de passe — normal pendant les tests, faux après le lancement.',
            'fix' => 'Définissez WEBX_SITE_GATE=false à l’ouverture du site.',
        ],
    ],
    'host' => [
        'mirror' => [
            'title' => 'Deux miroirs répondent',
            'found' => 'Le www et le nom nu répondent tous deux 200.',
            'why' => 'Chaque page existe en double, et les moteurs de recherche se partagent son poids entre les copies.',
            'fix' => 'Redirigez le second nom vers le principal par une seule redirection 301 dans le serveur web.',
        ],
        'https' => [
            'title' => 'http ne mène pas à https en une étape',
            'found' => 'http:// répond lui-même, mène ailleurs ou atteint https par une chaîne.',
            'why' => 'Les visiteurs arrivent sur une copie non sécurisée, et chaque étape de plus coûte du temps et du poids de lien.',
            'fix' => 'Une seule redirection 301 de http:// vers https:// de l’hôte principal, dans le serveur web.',
        ],
        'tls' => [
            'title' => 'Problème de certificat',
            'found' => 'Le certificat expire bientôt, désigne un autre hôte ou n’est pas de confiance.',
            'why' => 'Les navigateurs affichent un avertissement en pleine page et la plupart des visiteurs partent.',
            'fix' => 'Renouvelez le certificat (vérifiez que le renouvellement automatique fonctionne) et servez la chaîne complète pour cet hôte.',
        ],
        'hsts' => [
            'title' => 'Pas de HSTS',
            'found' => 'Pas d’en-tête Strict-Transport-Security.',
            'why' => 'La première visite peut encore passer en http simple et être interceptée.',
            'fix' => 'Ajoutez Strict-Transport-Security: max-age=31536000 dans le serveur web une fois https stable.',
        ],
        'index_files' => [
            'title' => 'Les fichiers index répondent',
            'found' => '/index.php ou un autre fichier index répond 200.',
            'why' => 'La page est disponible à une seconde adresse — un doublon pour les moteurs de recherche.',
            'fix' => 'Redirigez les fichiers index vers l’adresse sans eux par une redirection 301.',
        ],
        'slashes' => [
            'title' => 'Les doubles barres obliques ne sont pas fusionnées',
            'found' => 'Une adresse avec // répond 200.',
            'why' => 'Tout lien mal saisi crée une copie de plus de la page.',
            'fix' => 'Redirigez les adresses à barres obliques répétées vers l’adresse fusionnée par une redirection 301.',
        ],
        'trailing_slash' => [
            'title' => 'Avec et sans barre oblique finale',
            'found' => 'La même page répond avec et sans barre oblique finale.',
            'why' => 'Deux adresses pour une page se partagent son poids.',
            'fix' => 'Choisissez une forme et redirigez l’autre par une redirection 301.',
        ],
        'case' => [
            'title' => 'La casse n’est pas normalisée',
            'found' => 'Une adresse avec des majuscules répond 200.',
            'why' => 'Un lien saisi avec une autre casse crée un doublon.',
            'fix' => 'Redirigez les adresses avec majuscules vers celle en minuscules par une redirection 301.',
        ],
        'soft_404' => [
            'title' => 'Les pages absentes ne répondent pas 404',
            'found' => 'Une adresse qui ne peut pas exister répond 200 ou redirige.',
            'why' => 'Les moteurs de recherche indexent les fautes de frappe et les pages supprimées comme de vraies pages.',
            'fix' => 'Répondez 404 pour les adresses inconnues ; ne les redirigez pas vers l’accueil.',
        ],
        '404_page' => [
            'title' => 'La page 404 ne mène nulle part',
            'found' => 'La page 404 n’a pas de lien vers l’accueil.',
            'why' => 'Un visiteur qui a suivi un lien cassé ne sait plus où aller.',
            'fix' => 'Ajoutez au modèle 404 un lien vers l’accueil, la recherche ou les sections principales.',
        ],
        'compression' => [
            'title' => 'HTML sans compression',
            'found' => 'Les pages sont envoyées sans gzip ni brotli.',
            'why' => 'Les pages pèsent plusieurs fois plus et s’ouvrent plus lentement, surtout sur mobile.',
            'fix' => 'Activez gzip ou brotli pour text/html dans le serveur web.',
        ],
        'security_headers' => [
            'title' => 'Les en-têtes de sécurité manquent',
            'found' => 'Certains parmi X-Content-Type-Options, Referrer-Policy et la protection contre l’inclusion en cadre manquent.',
            'why' => 'Ils bloquent des attaques faciles : détection MIME, fuite d’adresses, clickjacking.',
            'fix' => 'Ajoutez les en-têtes dans le serveur web : nosniff, strict-origin-when-cross-origin, SAMEORIGIN.',
        ],
        'server_leak' => [
            'title' => 'Le serveur dévoile ses versions',
            'found' => 'X-Powered-By, ou Server avec un numéro de version.',
            'why' => 'Une carte toute prête pour qui cherche une faille connue dans cette version.',
            'fix' => 'Désactivez expose_php et server_tokens (ou leurs équivalents).',
        ],
        'static_cache' => [
            'title' => 'Les fichiers statiques ne sont pas mis en cache',
            'found' => 'CSS, JS ou images sans Cache-Control ou mis en cache moins d’une semaine.',
            'why' => 'Chaque page les télécharge de nouveau.',
            'fix' => 'Donnez aux fichiers statiques versionnés un long Cache-Control (un an, immutable) dans le serveur web.',
        ],
    ],
    'hosts' => [
        'dev_content' => [
            'title' => 'Liens vers un environnement de développement dans le contenu',
            'found' => 'Une adresse d’environnement de développement dans un enregistrement — publié, en brouillon, ou dans un champ que le modèle n’affiche pas.',
            'why' => 'Le contenu saisi sur un environnement de développement part en production avec des liens et des images qui y renvoient ; les visiteurs obtiennent des erreurs, et cet environnement est indexé.',
            'fix' => 'Ouvrez l’enregistrement et remplacez l’adresse de l’environnement par celle du site ou par un lien relatif. Listez les environnements dans les réglages de l’audit pour tous les repérer.',
        ],
    ],
];
