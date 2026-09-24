<?php

declare(strict_types=1);

/*
 * A field that points at records of another section (`wx-relations`), and the same choice as a
 * block's filter ("only related to" in `wx-collection`). The keys are the panel's: the English
 * here is what `messages.ts` carries.
 */

return [
    'field-add' => 'Ajouter…',
    'field-searching' => 'Recherche…',
    'field-nothing' => 'Rien n’a été trouvé.',
    'field-empty' => 'Rien n’est encore choisi.',
    'field-remove' => 'Retirer',
    'field-drag' => 'Faites glisser pour changer l’ordre',
    'field-hidden' => 'Pas sur le site',
    'field-trashed' => 'Dans la corbeille',
    'field-missing' => 'Introuvable',
    'field-full' => 'Pas plus de :max ne peuvent être choisis.',
    'field-forbidden' => 'Vous ne pouvez pas voir ces éléments : le choix ne peut pas être modifié ici.',
    'collection-related' => 'Seulement liés à',
    'collection-related-to' => 'Seulement liés à « :target »',
    'collection-related-type' => 'Quelle section',
    'collection-related-any' => 'Non restreint : tous les éléments, liés ou non.',
    'collection-related-current' => 'L’élément de la page où il se trouve',
    'collection-related-current-hint' => 'Sur la page d’un élément de « :target », le bloc montre ce qui lui est lié ; sur toute autre page, il ne montre rien.',
];
