<?php

declare(strict_types=1);

// The regions of the layout (the regions spec): the words a declaration may point at with
// `trans::webx-blocks::regions.header`, and what the server says about a region.
return [
    'header' => 'En-tête',
    'footer' => 'Pied de page',
    'usage' => 'Zone « :title »',
    'preview-failed' => 'Un bloc de cette zone échoue. Sur le site, toute la zone affichera à la place le balisage du code.',
    'not-in-registry' => ':path n’est pas une page du registre d’adresses du site : la zone est montrée sur une page vide de la mise en page.',
    'too-many' => 'La zone contient au plus :max blocs.',
    'not-allowed' => 'Le bloc « :type » ne peut pas être placé dans cette zone.',
    'refused' => 'La zone n’accepte pas ces blocs.',
    'conflict' => 'La zone a été modifiée depuis son ouverture. Rechargez-la pour voir les changements.',
    'failed-block' => 'Le bloc « :type » (:key) échoue : :reason',
    'not-published' => 'Non publié : un bloc du brouillon ne s’affiche pas.',
    'nothing-to-publish' => 'La zone n’a jamais été enregistrée : il n’y a rien à publier.',
    'no-version' => 'La zone n’a pas de version :number.',
    'no-fallback' => 'La mise en page n’a pas encore nommé de vue pour cette zone, ou la vue a disparu.',
    'adopt-taken' => 'Un type de bloc « :slug » existe déjà.',
    'adopt-failed' => 'Le balisage n’a pas pu devenir un type de bloc.',
    'adopt-forbidden' => 'Déplacer le balisage dans un type de bloc demande le droit de modifier les types de blocs.',
];
