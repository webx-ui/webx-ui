<?php

declare(strict_types=1);

return [
    'currency' => 'Un flux utilise la devise du site : :currency.',
    'currency-unset' => 'Le site n’a pas de devise : définissez WEBX_CATALOG_CURRENCY.',
    'filter-facet' => 'Le filtre du flux cite [:facet], que le site n’a pas.',
    'source-property' => 'Le flux prend le MPN de la propriété [:property], que le site n’a pas.',
    'no-item' => 'Le flux n’a pas de modèle de produit.',
    'template' => 'Le modèle est illisible : :reason',
    'too-large' => 'Le fichier dépasse la taille permise d’un flux.',
    'stale' => 'La génération n’avançait plus et a été fermée.',
    'file-xml' => 'Le fichier n’est pas un XML valide, ligne :line : :reason',
    'file-json' => 'L’en-tête et le pied ne forment pas un JSON valide : :reason',
    'file-json-item' => 'Le produit :product ne forme pas un JSON valide : :reason',
    'file-csv' => 'La ligne :line du fichier a :found champs au lieu de :expected.',
];
