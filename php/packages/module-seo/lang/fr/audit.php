<?php

declare(strict_types=1);

return [
    'on' => 'Activé',
    'normalise-note' => 'Fonctionne pour les requêtes que le serveur web transmet au site. Si le constat subsiste après la prochaine exécution, le serveur web répond lui-même à cette adresse — configurez la redirection là-bas.',
    'robots-file-note' => 'Le site a un fichier public/robots.txt. Le serveur web le sert avant d’interroger le site : supprimez-le pour que le réglage prenne effet.',
    'redirect-chain' => 'Sauts avant la page : :steps',
    'title-duplicate' => '« :title » — dans :count fiches',
    'redirect-broken' => ':target répond :status',
    'rule-dead' => 'L’adresse répond :status',
];
