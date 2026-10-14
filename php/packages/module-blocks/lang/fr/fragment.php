<?php

declare(strict_types=1);

return [
    'choose' => 'Choisir un bloc partagé',
    'none' => 'Le site n’a pas encore de blocs partagés. Un développeur en déclare un sous regions dans config/webx-blocks.php, avec fragment à true.',
    'missing' => '« :name » n’est plus un bloc partagé du site : rien ne s’affiche ici.',
    'status-live' => 'Sur le site',
    'status-edits' => 'Sur le site, modifications en attente',
    'status-fallback' => 'Non publié : le site affiche sa vue de secours',
    'status-empty' => 'Vide',
    'explain' => 'Ce bloc affiche un bloc partagé du site. Le modifier le change sur toutes les pages où il figure.',
    'edit' => 'Modifier pour toutes les pages',
    'detach' => 'Le rendre propre à cette page',
    'detach-title' => 'Rendre « :title » propre à cette page ?',
    'detach-text' => 'Ses blocs sont copiés dans cette page à sa place, tels qu’ils sont sur le site. Ils ne suivent plus le bloc partagé : ses modifications n’atteindront plus cette page.',
    'detached' => 'Les blocs de « :title » sont désormais propres à cette page.',
    'unpublished' => 'Le bloc partagé n’est pas encore publié : il n’y a rien à copier.',
    'group-layout' => 'En-tête et pied de page',
    'group-shared' => 'Blocs partagés',
    'group-shared-help' => 'Modifiés une fois ici et affichés partout où une page ou un gabarit du site les place.',
    'count' => 'Pages : :count',
    'used-on' => 'Où il figure',
    'used-nowhere' => 'Aucune page ne le place encore.',
    'templates' => 'Les gabarits du site qui l’impriment par une balise ne sont pas listés.',
    'draft-only' => 'Dans un brouillon',
    'show-here' => 'Prévisualiser sur cette page',
    'publish-text' => 'Chaque page qui le place affiche ces blocs au lieu de la vue du code.',
];
