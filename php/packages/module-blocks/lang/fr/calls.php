<?php

declare(strict_types=1);

return [
    'kind' => 'Un type est soit un bloc, soit un composant.',
    'kind-in-use' => 'Le bloc figure sur des pages et ne peut pas devenir un composant. Pages : :count.',
    'unknown-call' => '« :type » n’est pas un type de bloc : l’appel n’affiche rien sur le site.',
    'dynamic-call' => 'Le type appelé n’est pas écrit en toutes lettres : le publier ne vérifiera pas ce modèle.',
    'delete-used-by' => 'D’autres types appellent celui-ci, et leurs modèles laisseraient un vide. Retirez d’abord les appels.',
    'delete-used-by-one' => 'Appelé par « :title » (:slug)',
    'publish-cycle' => 'Les types s’appellent en boucle : :path.',
    'publish-breaks-parent' => 'Casse « :parent » sur son exemple : :reason',
    'publish-breaks-parent-on' => 'Casse « :parent » sur « :entity » : :reason',
    'publish-breaks-declared' => 'Casse l’endroit d’où le module :module l’appelle : :reason',
    'customise-exists' => 'Un type avec cet identifiant existe déjà.',
    'customised-from' => 'Depuis :view',
];
