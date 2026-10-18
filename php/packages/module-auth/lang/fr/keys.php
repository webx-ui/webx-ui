<?php

declare(strict_types=1);

/*
 * Keys for programs: issued under Administrators → Keys, shown once, revoked there. The words
 * are about a key a program holds, not about tokens and scopes underneath — except the scopes
 * themselves, which are kept as issued because an agent's refusal quotes them.
 */

return [
    'title' => 'Clés',
    'issue' => 'Émettre une clé',
    'issue-short' => 'Émettre',
    'lead' => 'Une clé permet à un programme — un veilleur de file, un import nocturne — de travailler dans ce panneau sans navigateur. Elle agit comme l\'administrateur que vous choisissez et ne peut jamais faire plus que lui. Une clé est un programme, pas une personne : ce qu\'un module réserve à une personne, comme une étape vérifiée à la main, elle ne peut pas le faire.',
    'name' => 'Nom',
    'name-hint' => 'Ce qu\'est le programme, pour que la liste le dise : « veilleur de file », « import nocturne ».',
    'who' => 'Agit comme',
    'who-hint' => 'Tout ce que la clé modifie est fait au nom de cet administrateur, dans la limite de ses droits.',
    'allowed' => 'Ce qui est permis',
    'everything' => 'Tout ce que l\'administrateur peut faire',
    'chosen' => 'Seulement les scopes choisis ci-dessous',
    'scopes' => 'Scopes',
    'scopes-hint' => 'Un module installé plus tard reste hors de portée tant qu\'une nouvelle clé ne le nomme pas.',
    'scopes-required' => 'Choisissez au moins un scope.',
    'lifetime' => 'Durée de vie',
    'lifetime-hint' => 'Une clé ne se prolonge pas : émettez-en une nouvelle avant que celle-ci n\'expire, puis révoquez l\'ancienne.',
    'days-30' => '30 jours',
    'days-90' => '90 jours',
    'days-365' => '1 an',
    'days-1095' => '3 ans',
    'days-3650' => '10 ans',
    'issued-title' => 'Copiez la clé maintenant',
    'issued-text' => 'C\'est la seule fois que la clé est affichée. Personne ne pourra la revoir, ni ici ni sur le serveur. Perdue, révoquez-la et émettez-en une autre.',
    'secret' => 'Clé',
    'copy' => 'Copier',
    'copied' => 'Copiée',
    'copy-failed' => 'Impossible d\'accéder au presse-papiers. Sélectionnez la clé et copiez-la à la main.',
    'address' => 'Adresse du serveur',
    'header-hint' => 'Le programme envoie la clé dans un en-tête : Authorization: Bearer <clé>.',
    'done' => 'Terminé',
    'cancel' => 'Annuler',
    'expires' => 'Expire',
    'until' => 'jusqu\'au',
    'last-used' => 'Dernière utilisation',
    'never-used' => 'Jamais',
    'issued-by' => 'Émise par :name',
    'issued-console' => 'Émise dans un terminal',
    'full' => 'Tout ce que :name peut faire',
    'deleted' => 'Administrateur n° :id',
    'revoke' => 'Révoquer',
    'revoked' => 'Révoquée',
    'expired' => 'Expirée',
    'confirm-title' => 'Révoquer « :name » ?',
    'confirm-text' => 'Le programme qui l\'utilise sera refusé dès son prochain appel. Une clé révoquée ne peut pas être réactivée.',
    'confirm' => 'Révoquer',
    'revoked-done' => '« :name » est révoquée',
    'empty' => 'Aucune clé n\'a été émise',
    'lead-mine' => 'Une clé permet à un programme — un veilleur de file, un import de nuit — de travailler dans ce panneau en votre nom, sans navigateur. Elle ne peut jamais plus que vous, et seulement dans les périmètres choisis.',
    'empty-mine' => 'Vous n’avez pas encore de clé',

];
