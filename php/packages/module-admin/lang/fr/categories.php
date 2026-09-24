<?php

declare(strict_types=1);

/*
 * The words of the categories every module shares (`WebxUi\Admin\Categories`). A module that
 * calls its categories something else — rubrics — says its own refusal instead.
 */

return [
    'in-use' => 'Entrées dedans : :count. Déplacez-les d’abord dans une autre catégorie.',
    'slug-shape' => 'Des lettres, des chiffres et des tirets isolés entre eux.',

    // The shared screens of categories (`categories/` in @webx-ui/module-admin). A module
    // that calls its categories something else hands its own keys in over these.
    'new' => 'Nouvelle catégorie',
    'empty' => 'Pas encore de catégories.',
    'empty-help' => 'Une catégorie regroupe des entrées. Une entrée peut être dans plusieurs.',
    'order' => 'L’ordre ici est l’ordre sur le site',
    'hidden' => 'Masquée sur le site',
    'no-address' => 'Pas d’adresse dans cette langue',
    'count' => 'Entrées : :count',
    'show-items' => 'Voir ses entrées',
    'edit' => 'Modifier',
    'open-on-site' => 'Ouvrir sur le site',
    'delete' => 'Supprimer',
    'delete-blocked' => 'Tant qu’elle contient des entrées, elle ne peut pas être supprimée — déplacez-les d’abord.',
    'delete-title' => 'Supprimer « :name » ?',
    'delete-text' => 'Elle part à la corbeille et quitte le site, et son adresse redevient libre.',
    'deleted' => 'La catégorie est à la corbeille.',
    'cancel' => 'Annuler',
    'create' => 'Créer',
    'save' => 'Enregistrer',
    'saved' => 'Enregistré.',
    'save-failed' => 'Non enregistré — regardez les champs marqués.',
    'reorder-failed' => 'Le nouvel ordre n’a pas été enregistré.',
    'field-title' => 'Nom',
    'field-slug' => 'Adresse',
    'address-moving' => 'L’adresse change. L’ancienne continue de fonctionner et mène à la nouvelle.',
    'untitled' => 'Sans titre',
    'trail' => 'Où vous êtes',
    'leave-title' => 'Quitter sans enregistrer ?',
    'leave-text' => 'Ce qui a été modifié ici depuis le dernier enregistrement sera perdu.',
    'leave' => 'Quitter',
    'field-main' => 'Principale',
    'field-add' => 'Ajouter une catégorie',
    'field-remove' => 'Retirer de cette catégorie',
    'field-empty' => 'Dans aucune catégorie pour l’instant.',
    'field-none-left' => 'Toutes les catégories sont déjà choisies.',
    'order-all' => 'Faites glisser pour changer l’ordre sur le site.',
    'order-category' => 'Faites glisser pour changer l’ordre dans cette catégorie. Le reste de la liste garde le sien.',
    'order-locked' => 'Videz la recherche et les filtres pour changer l’ordre — on ne fait glisser que la liste entière ou une catégorie.',
    'unknown' => 'L’une des catégories choisies n’existe plus.',
];
