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
        'dev_page' => [
            'title' => 'Liens vers un environnement de développement sur la page',
            'found' => 'Un lien ou une ressource de la page — une image, un script, une feuille de style, og:image, le canonical — mène à un environnement de développement.',
            'why' => 'Les visiteurs suivent des liens vers un site qui ne leur est pas destiné, les images cassent quand l’environnement s’arrête, et les moteurs de recherche trouvent cet environnement par le site.',
            'fix' => 'Repérez l’adresse dans le contenu ou le modèle de la page et remplacez l’environnement par l’hôte du site ou par un lien relatif.',
        ],
        'similar' => [
            'title' => 'Un hôte qui ressemble à ce site',
            'found' => 'Un lien vers un hôte dont le premier mot est le même que celui du site, dans une autre zone, comme shop.local à côté de shop.com.',
            'why' => 'C’est très probablement un environnement ou une ancienne copie du site que l’audit ne connaît pas.',
            'fix' => 'S’il s’agit d’un environnement ou d’un ancien domaine, ajoutez-le à « Autres adresses de ce site » dans les réglages de l’audit et corrigez les liens ; si c’est le site de quelqu’un d’autre, il n’y a rien à faire.',
        ],
        'wrong_mirror' => [
            'title' => 'Liens via un autre miroir',
            'found' => 'Un lien vers le site via son autre miroir (www ou le nom sans www) ou en http sur un site en https.',
            'why' => 'Chaque clic passe par une redirection : plus lent pour les visiteurs, et les moteurs de recherche voient des liens vers une adresse qui n’est pas celle de la page.',
            'fix' => 'Faites le lien vers le miroir principal en https, ou utilisez des liens relatifs.',
        ],
        'absolute_own' => [
            'title' => 'Liens absolus vers le site lui-même',
            'found' => 'Un lien ou une image du contenu est écrit avec l’hôte du site au lieu d’un chemin.',
            'why' => 'Cela fonctionne aujourd’hui et casse au prochain changement de domaine ou de protocole ; copié sur un environnement de développement, il ramène vers le site en production.',
            'fix' => 'Écrivez les liens vers les pages du site sous forme de chemins : /about au lieu de https://shop.com/about.',
        ],
        'new_domain' => [
            'title' => 'Un nouveau domaine externe',
            'found' => 'Le site renvoie vers un domaine vers lequel il ne renvoyait pas lors de la précédente exécution complète.',
            'why' => 'Un nouveau domaine est généralement un nouveau lien ajouté par quelqu’un — et parfois une faute de frappe, ou des liens de spam laissés par quelqu’un qui s’est introduit dans le site.',
            'fix' => 'Ouvrez les pages listées et vérifiez que le lien doit bien s’y trouver.',
        ],
        'external_many' => [
            'title' => 'Beaucoup de liens externes sur une page',
            'found' => 'La page a plus de liens externes que le seuil.',
            'why' => 'Une page faite surtout de liens vers d’autres sites ressemble à une ferme de liens pour les moteurs de recherche, et c’est souvent un signe de spam.',
            'fix' => 'Supprimez les liens qui n’aident pas le visiteur, ou scindez la page.',
        ],
        'blank_opener' => [
            'title' => 'Nouvel onglet sans noopener',
            'found' => 'Un lien vers un autre site s’ouvre dans un nouvel onglet sans rel="noopener".',
            'why' => 'Dans les anciens navigateurs, la page ouverte peut rediriger l’onglet du site vers une page de son choix.',
            'fix' => 'Ajoutez rel="noopener" (ou noreferrer) aux liens avec target="_blank".',
        ],
    ],
    'indexing' => [
        'home_noindex' => [
            'title' => 'La page d’accueil est fermée aux moteurs de recherche',
            'found' => 'La page d’accueil a noindex dans la balise meta robots ou dans l’en-tête X-Robots-Tag.',
            'why' => 'La page la plus importante du site disparaît de la recherche, et souvent le site entier suit.',
            'fix' => 'Retirez noindex de la page d’accueil : vérifiez les réglages SEO de la page, la mise en page et les en-têtes du serveur web.',
        ],
        'noindex' => [
            'title' => 'Pages fermées avec noindex',
            'found' => 'La page a noindex dans la balise meta robots ou dans l’en-tête X-Robots-Tag.',
            'why' => 'Les moteurs de recherche retirent la page. Normal pour les résultats de recherche et les pages de service, anormal pour un contenu fermé par erreur.',
            'fix' => 'Parcourez la liste ; ouvrez les pages qui doivent être trouvées dans leurs réglages SEO et retirez noindex.',
        ],
    ],
    'title' => [
        'missing' => [
            'title' => 'Pas de title',
            'found' => 'La page n’a pas de <title>, ou il est vide.',
            'why' => 'Le title est la ligne que les moteurs de recherche affichent comme lien vers la page ; sans lui, ils en inventent un.',
            'fix' => 'Donnez un title à la page dans ses réglages SEO, ou vérifiez que la mise en page en affiche un.',
        ],
        'duplicate' => [
            'title' => 'Titles en double',
            'found' => 'Plusieurs pages indexables ont le même title.',
            'why' => 'Les moteurs de recherche ne distinguent pas les pages et en montrent une, pas forcément la bonne.',
            'fix' => 'Donnez à chaque page un title propre qui dit ce qu’elle contient.',
        ],
        'length' => [
            'title' => 'Title trop court ou trop long',
            'found' => 'Le title est plus court ou plus long que les seuils, en caractères.',
            'why' => 'Un title long est tronqué dans les résultats de recherche (la limite est d’environ 600 pixels, soit à peu près 60 caractères) ; un title court en dit trop peu.',
            'fix' => 'Réécrivez le title pour qu’il tienne dans la plage des seuils de l’audit.',
        ],
        'multiple' => [
            'title' => 'Plus d’un title',
            'found' => 'La page a plus d’une balise <title>.',
            'why' => 'Les moteurs de recherche en prennent une, pas forcément celle écrite pour la page.',
            'fix' => 'Trouvez quel modèle ou quel bloc affiche le second title et supprimez-le.',
        ],
    ],
    'description' => [
        'missing' => [
            'title' => 'Pas de meta description',
            'found' => 'La page n’a pas de meta description, ou elle est vide.',
            'why' => 'Les moteurs de recherche composent l’extrait sous le lien à partir du texte qu’ils trouvent.',
            'fix' => 'Rédigez une description dans les réglages SEO de la page : ce que la page propose, en une ou deux phrases.',
        ],
        'duplicate' => [
            'title' => 'Descriptions en double',
            'found' => 'Plusieurs pages indexables ont la même meta description.',
            'why' => 'Le même extrait sous des liens différents n’apprend rien à celui qui cherche, et les moteurs de recherche le remplacent par le leur.',
            'fix' => 'Rédigez une description propre à chaque page.',
        ],
        'length' => [
            'title' => 'Description trop courte ou trop longue',
            'found' => 'La description est plus courte ou plus longue que les seuils, en caractères.',
            'why' => 'Une description longue est tronquée dans les résultats de recherche (environ 920 pixels, soit à peu près 160 caractères) ; une description courte est souvent remplacée.',
            'fix' => 'Réécrivez la description pour qu’elle tienne dans la plage des seuils de l’audit.',
        ],
    ],
    'h1' => [
        'missing' => [
            'title' => 'Pas de H1',
            'found' => 'La page n’a pas de titre H1.',
            'why' => 'Le H1 dit aux visiteurs et aux moteurs de recherche de quoi parle la page ; les lecteurs d’écran s’en servent pour trouver le début du contenu.',
            'fix' => 'Donnez à la page un H1 — en général son nom — dans le modèle ou le contenu.',
        ],
        'multiple' => [
            'title' => 'Plus d’un H1',
            'found' => 'La page a plus d’un titre H1.',
            'why' => 'Ce n’est pas une erreur en soi, mais souvent le signe qu’un bloc ou le logo utilise H1 là où un niveau inférieur était voulu.',
            'fix' => 'Gardez un seul H1 pour le nom de la page et passez les autres en H2 ou moins.',
        ],
        'equals_title' => [
            'title' => 'H1 identique au title',
            'found' => 'Le H1 répète le title mot pour mot.',
            'why' => 'Deux endroits pour décrire la page disent la même chose ; l’un des deux pourrait ajouter un mot que les gens recherchent.',
            'fix' => 'Gardez le H1 court et lisible, et laissez le title porter les mots-clés et le nom du site.',
        ],
    ],
    'headings' => [
        'skipped' => [
            'title' => 'Niveau de titre sauté',
            'found' => 'Un niveau de titre est sauté en descendant, par exemple H2 suivi de H4.',
            'why' => 'Les lecteurs d’écran naviguent par les titres, et un trou se lit comme un contenu manquant.',
            'fix' => 'Utilisez les niveaux dans l’ordre ; choisissez l’aspect avec les styles, pas avec le niveau.',
        ],
    ],
    'canonical' => [
        'missing' => [
            'title' => 'Pas de canonical',
            'found' => 'Une page indexable n’a pas de lien canonical, ni dans la balise ni dans l’en-tête.',
            'why' => 'Sans lui, chaque copie de la page avec des paramètres de suivi ou de tri peut concurrencer la page elle-même.',
            'fix' => 'Faites afficher par la mise en page <link rel="canonical"> avec l’adresse propre de la page.',
        ],
        'relative' => [
            'title' => 'Canonical relatif',
            'found' => 'Le canonical est écrit comme un chemin, et non comme une adresse complète.',
            'why' => 'Les moteurs de recherche le lisent par rapport à l’adresse par laquelle ils sont venus, y compris un autre miroir ou un autre protocole.',
            'fix' => 'Affichez le canonical comme une adresse complète avec le schéma et l’hôte principal.',
        ],
        'multiple' => [
            'title' => 'Canonicals contradictoires',
            'found' => 'La page a plus d’un canonical, ou la balise et l’en-tête Link ne concordent pas.',
            'why' => 'Face à des canonicals contradictoires, les moteurs de recherche les ignorent tous.',
            'fix' => 'Ne gardez qu’un canonical : trouvez le modèle, le bloc ou la règle du serveur qui ajoute le second et supprimez-le.',
        ],
        'broken' => [
            'title' => 'Canonical vers une page cassée ou fermée',
            'found' => 'Le canonical mène à une redirection, une erreur ou une page avec noindex.',
            'why' => 'La page désigne un original qui ne peut pas être indexé, et les moteurs de recherche peuvent écarter les deux.',
            'fix' => 'Faites pointer le canonical vers l’adresse propre et fonctionnelle de la page, ou vers l’original en ligne.',
        ],
        'other' => [
            'title' => 'Canonical vers une autre page',
            'found' => 'Le canonical pointe vers une adresse différente de celle de la page.',
            'why' => 'La page demande à ne pas être indexée au profit d’une autre — normal pour les filtres et les copies, anormal pour une page qui doit être trouvée.',
            'fix' => 'Parcourez la liste ; pour les pages qui doivent être trouvées, faites du canonical leur propre adresse.',
        ],
    ],
    'html' => [
        'lang' => [
            'title' => 'Pas de langue de la page',
            'found' => 'La balise <html> n’a pas d’attribut lang.',
            'why' => 'Les lecteurs d’écran choisissent la voix d’après lui, les navigateurs proposent la traduction d’après lui, et les moteurs de recherche s’en servent comme indice.',
            'fix' => 'Affichez <html lang="…"> avec la langue de la page dans la mise en page.',
        ],
        'viewport' => [
            'title' => 'Pas de meta viewport',
            'found' => 'La page n’a pas de <meta name="viewport">.',
            'why' => 'Les téléphones affichent la page à la largeur d’un ordinateur, réduite ; les moteurs de recherche jugent une telle page non adaptée au mobile.',
            'fix' => 'Ajoutez <meta name="viewport" content="width=device-width, initial-scale=1"> à la mise en page.',
        ],
        'favicon' => [
            'title' => 'Pas d’icône',
            'found' => 'La page ne référence aucune icône.',
            'why' => 'Les onglets du navigateur, les favoris et les résultats de recherche sur téléphone montrent un carré vide au lieu de la marque du site.',
            'fix' => 'Ajoutez <link rel="icon"> à la mise en page.',
        ],
    ],
    'og' => [
        'missing' => [
            'title' => 'Balises Open Graph manquantes',
            'found' => 'La page n’a pas de og:title, og:image ou og:url.',
            'why' => 'Un lien partagé dans une messagerie ou un réseau social s’affiche comme une simple adresse, sans image ni titre.',
            'fix' => 'Renseignez l’aperçu social dans les réglages SEO de la page, ou faites afficher les balises par la mise en page.',
        ],
    ],
    'content' => [
        'thin' => [
            'title' => 'Peu de texte',
            'found' => 'Une page indexable a moins de mots que le seuil.',
            'why' => 'Les moteurs de recherche classent plus bas les pages qui ont peu à lire, et peuvent juger de faible qualité un grand nombre de telles pages.',
            'fix' => 'Ajoutez du texte utile au visiteur, fusionnez les pages minces, ou fermez-les avec noindex.',
        ],
        'text_ratio' => [
            'title' => 'Peu de texte par rapport au balisage',
            'found' => 'Le texte visible représente une part du HTML plus petite que le seuil.',
            'why' => 'La page est lourde pour ce qu’elle dit : lente sur un téléphone, et les moteurs de recherche trouvent peu de contenu dans beaucoup de code.',
            'fix' => 'Déplacez les scripts et styles intégrés dans des fichiers, supprimez le balisage inutilisé et ajoutez du contenu.',
        ],
        'duplicate' => [
            'title' => 'Texte en double',
            'found' => 'Plusieurs pages indexables ont le même texte visible.',
            'why' => 'Les moteurs de recherche choisissent une copie à afficher et ignorent les autres.',
            'fix' => 'Différenciez les pages, fusionnez-les, ou faites pointer le canonical des copies vers l’original.',
        ],
    ],
    'url' => [
        'length' => [
            'title' => 'Adresse longue',
            'found' => 'L’adresse est plus longue que le seuil.',
            'why' => 'Les adresses longues sont tronquées dans les résultats de recherche et difficiles à partager et à lire.',
            'fix' => 'Raccourcissez le slug de la page ; une redirection depuis l’ancienne adresse est ajoutée automatiquement.',
        ],
        'format' => [
            'title' => 'Format de l’adresse',
            'found' => 'Le chemin contient des majuscules, des tirets bas ou des caractères hors ASCII.',
            'why' => 'Les majuscules font de /About et /about deux pages, les tirets bas ne séparent pas les mots pour les moteurs de recherche, et les autres caractères deviennent %D0%B0 une fois copiés.',
            'fix' => 'Utilisez dans les slugs des lettres latines minuscules, des chiffres et des tirets.',
        ],
        'params' => [
            'title' => 'Paramètres sans canonical',
            'found' => 'Une adresse indexable a des paramètres de requête et pas de canonical.',
            'why' => 'Chaque combinaison de filtres et de tri devient une page à part dans les moteurs de recherche, qui répartissent le poids de la vraie page.',
            'fix' => 'Affichez un canonical vers l’adresse sans paramètres, ou fermez ces adresses avec noindex.',
        ],
    ],
    'perf' => [
        'ttfb' => [
            'title' => 'Réponse lente',
            'found' => 'La page a mis plus longtemps que le seuil à répondre.',
            'why' => 'Les visiteurs attendent avant que quoi que ce soit s’affiche, et les moteurs de recherche explorent moins un site lent.',
            'fix' => 'Activez les caches (config, routes, vues, pages), vérifiez les requêtes lentes et déplacez le travail lourd vers la file.',
        ],
        'html_size' => [
            'title' => 'HTML lourd',
            'found' => 'Le HTML de la page est plus gros que le seuil.',
            'why' => 'Les téléphones le téléchargent et l’analysent lentement ; les moteurs de recherche peuvent cesser de lire avant la fin.',
            'fix' => 'Paginez les longues listes, déplacez les données intégrées et le SVG dans des fichiers, supprimez les copies cachées du contenu.',
        ],
    ],
    'links' => [
        'broken' => [
            'title' => 'Liens internes cassés',
            'found' => 'Un lien vers une page du site répond 4xx, 5xx ou rien.',
            'why' => 'Les visiteurs tombent sur une erreur, et les moteurs de recherche y gaspillent leur visite.',
            'fix' => 'Corrigez ou supprimez le lien, ou ajoutez une redirection de l’adresse manquante vers la bonne page.',
        ],
        'empty' => [
            'title' => 'Liens sans texte',
            'found' => 'Un lien n’a ni texte ni aria-label, et un lien-image n’a pas d’alt.',
            'why' => 'Les lecteurs d’écran lisent l’adresse ou simplement « lien », et les moteurs de recherche n’apprennent rien sur la page vers laquelle il mène.',
            'fix' => 'Donnez au lien un texte, un aria-label, ou un alt à son image.',
        ],
        'nofollow_internal' => [
            'title' => 'nofollow sur des liens internes',
            'found' => 'Un lien vers une page du site a rel="nofollow".',
            'why' => 'Le site demande aux moteurs de recherche de ne pas suivre ses propres liens, et la page reçoit moins de poids.',
            'fix' => 'Retirez nofollow des liens vers les pages du site.',
        ],
    ],
    'mixed_content' => [
        'title' => 'Contenu mixte',
        'found' => 'Une page en https charge une ressource en http.',
        'why' => 'Les navigateurs bloquent ces scripts et styles et avertissent pour les images ; le cadenas disparaît.',
        'fix' => 'Chargez la ressource en https, ou utilisez un chemin sans schéma.',
    ],
    'forms' => [
        'insecure' => [
            'title' => 'Formulaire envoyé en http',
            'found' => 'Un formulaire est envoyé vers une adresse http.',
            'why' => 'Ce que les visiteurs saisissent circule en clair, et les navigateurs avertissent avant l’envoi.',
            'fix' => 'Faites pointer le formulaire vers une adresse https ou vers un chemin.',
        ],
    ],
    'images' => [
        'alt' => [
            'title' => 'Images sans alt',
            'found' => 'Un <img> n’a pas d’attribut alt.',
            'why' => 'Les lecteurs d’écran lisent le nom du fichier, et les moteurs de recherche ne savent pas ce que montre l’image. Un alt vide pour une image décorative convient.',
            'fix' => 'Décrivez l’image dans son alt, ou définissez alt="" s’il s’agit d’une décoration.',
        ],
        'dimensions' => [
            'title' => 'Images sans dimensions',
            'found' => 'Un <img> n’a ni width ni height.',
            'why' => 'La page saute pendant le chargement des images, et les visiteurs cliquent au mauvais endroit.',
            'fix' => 'Affichez width et height des images dans le modèle ; le CSS peut tout de même les rendre adaptatives.',
        ],
    ],
    'a11y' => [
        'button_name' => [
            'title' => 'Boutons sans nom',
            'found' => 'Un bouton n’a ni texte, ni aria-label, ni title.',
            'why' => 'Un lecteur d’écran ne peut dire que « bouton », et son utilisateur ne sait pas ce qu’il fait.',
            'fix' => 'Donnez un texte au bouton, ou un aria-label si c’est une icône.',
        ],
        'form_label' => [
            'title' => 'Champs sans libellé',
            'found' => 'Un champ de formulaire n’a ni label ni aria-label.',
            'why' => 'Un lecteur d’écran ne peut pas dire quoi saisir ; un texte indicatif disparaît dès que la saisie commence.',
            'fix' => 'Ajoutez un <label for="…"> à chaque champ, ou un aria-label.',
        ],
        'iframe_title' => [
            'title' => 'Cadres sans titre',
            'found' => 'Un iframe n’a pas de title.',
            'why' => 'Les lecteurs d’écran annoncent un cadre sans nom, et leurs utilisateurs ne peuvent pas distinguer une carte d’une vidéo.',
            'fix' => 'Ajoutez un title qui dit ce que le cadre montre.',
        ],
    ],
    'structure' => [
        'depth' => [
            'title' => 'Pages profondes',
            'found' => 'Une page indexable est plus loin de la page d’accueil que le seuil, en clics.',
            'why' => 'Les moteurs de recherche visitent moins souvent les pages profondes et leur accordent moins de valeur ; les visiteurs y arrivent rarement.',
            'fix' => 'Faites un lien vers la page depuis une catégorie, le menu ou des pages associées.',
        ],
        'orphan' => [
            'title' => 'Pages orphelines',
            'found' => 'La page figure dans le sitemap ou le registre d’adresses, mais aucune page du site ne renvoie vers elle.',
            'why' => 'Les visiteurs ne peuvent pas l’atteindre, et les moteurs de recherche jugent sans importance une page vers laquelle rien ne renvoie.',
            'fix' => 'Faites un lien vers la page depuis l’endroit approprié, ou dépubliez-la si elle ne sert pas.',
        ],
        'dead_end' => [
            'title' => 'Impasses',
            'found' => 'La page ne renvoie vers aucune autre page du site.',
            'why' => 'Un visiteur qui y arrive ne peut que revenir en arrière.',
            'fix' => 'Vérifiez que la mise en page avec son menu est utilisée, et ajoutez des liens vers des pages associées.',
        ],
    ],
];
