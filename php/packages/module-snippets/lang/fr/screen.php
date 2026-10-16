<?php

declare(strict_types=1);

return [
    'name' => 'Nom',
    'name-help' => 'Pour les administrateurs seulement, jamais affiché sur le site.',
    'place' => 'Emplacement',
    'place-help' => 'Où le code est imprimé dans la page. Vos propres emplacements se déclarent dans config/webx-snippets.php.',
    'code' => 'Code',
    'code-help' => 'Collez exactement ce que le service a fourni, sans <html>, <head> ni <body>. document.write ne fonctionne pas dans un code qui attend le consentement.',
    'settings' => 'Réglages',
    'enabled' => 'Activé',
    'enabled-help' => 'Un code activé est sur le site dès qu’il est enregistré.',
    'consent' => 'Consentement aux cookies',
    'consent-help' => 'Tout sauf « Nécessaires » n’est imprimé qu’après l’accord du visiteur pour cette catégorie.',
    'on-stage' => 'Aussi en dev et stage',
    'on-stage-help' => 'Désactivé : le code n’est imprimé qu’en production, une copie du site ne compte pas de visites.',
    'coverage' => 'Où il est imprimé',
    'only-paths' => 'Seulement sur',
    'only-paths-help' => 'Chemins sans la langue, * pour n’importe quoi : /, /blog/*. Vide signifie tout le site.',
    'except-paths' => 'Sauf',
    'except-paths-help' => 'Chemins où le code n’est jamais imprimé, par exemple /blog/drafts/*.',
    'locales' => 'Langues',
    'locales-help' => 'Rien de coché signifie toutes les langues.',
    'history' => 'Historique',
    'position' => 'Ordre',
];
