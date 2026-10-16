<?php

declare(strict_types=1);

return [
    'required' => 'Le champ « :field » ne peut pas être vide.',
    'name-long' => 'Le nom dépasse :max caractères.',
    'code-long' => 'Le code fait :bytes octets ; la limite est :max.',
    'consent' => 'La catégorie de consentement est l’une de : :categories.',
    'mask' => 'Un chemin part de la racine, comme « /blog/* », sans l’adresse du site ni « ? » : [:mask] n’en est pas un.',
    'masks-many' => 'Pas plus de :max chemins dans une liste.',
    'locales' => 'Le site n’est pas publié en :unknown. Il a : :known.',
    'boolean' => 'Le champ « :field » vaut oui ou non.',
    'list' => 'Une liste de chemins ou de langues est attendue.',
    'place-undeclared' => 'L’emplacement « :place » n’est pas déclaré dans config/webx-snippets.php.',
    'place-orphaned' => 'L’emplacement « :place » n’est plus déclaré, le code ne serait imprimé nulle part : déplacez-le ou désactivez-le.',
    'document-tags' => 'Le code contient :tags : collez seulement ce que le service demande de mettre dans la page.',
    'unknown' => 'Aucun code n’a l’id :ids.',
    'reorder' => 'L’ordre est un emplacement et la liste de ses codes.',
];
