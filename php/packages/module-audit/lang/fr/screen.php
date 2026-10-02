<?php

declare(strict_types=1);

return [
    'tab' => 'Audit',
    'base-url' => 'Adresse à auditer',
    'base-url-help' => 'Vide signifie APP_URL. L’audit demande au site ses pages à cette adresse.',
    'resolve-to' => 'Se connecter à',
    'resolve-to-help' => 'Une adresse IP ou un hôte vers lequel ouvrir la connexion, le nom public restant dans la requête. Vide signifie ce que répond le DNS. Pour Docker et les serveurs derrière un NAT.',
    'other-hosts' => 'Autres adresses de ce site',
    'other-hosts-help' => 'Environnements de développement, de préproduction et anciens domaines, un par ligne. Un lien vers l’un d’eux est une erreur, où qu’il soit trouvé.',
    'fix-unavailable' => 'Cette correction ne peut plus résoudre ce constat. Relancez l’audit.',
];
