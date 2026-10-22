<?php

declare(strict_types=1);

return [
    'title' => 'Index de recherche',
    'lead' => 'Le catalogue cherche, filtre et compte avec Manticore. Quand Manticore ne répond pas, le panneau répond depuis la base de données.',
    'refresh' => 'Actualiser',
    'load-failed' => 'Impossible de charger l’état de l’index.',

    'connection' => 'Serveur',
    'address' => 'Adresse',
    'prefix' => 'Préfixe des tables',
    'version' => 'Version',
    'available' => 'Répond',
    'unavailable' => 'Ne répond pas',

    'tables' => 'Tables',
    'language' => 'Langue',
    'table' => 'Table',
    'in-index' => 'Dans l’index',
    'in-database' => 'Dans la base de données',
    'state' => 'État',
    'state-ready' => 'À jour',
    'state-stale' => 'Schéma obsolète',
    'state-missing' => 'Absente',
    'filling' => 'Une reconstruction remplit la table à côté.',
    'filled' => 'Une reconstruction remplit la table à côté : :done sur :total',
    'no-tables' => 'Le serveur n’a pas dit quelles tables il a.',

    'queue' => 'File d’attente',
    'waiting' => 'En attente d’écriture',
    'oldest' => 'La plus ancienne depuis',
    'queue-empty' => 'Chaque produit enregistré est dans l’index.',

    'outdated' => 'L’index n’a pas le schéma que le catalogue écrit maintenant',
    'outdated-text' => 'En attendant, la vitrine répond depuis les anciennes tables, mais ce qui leur manque — un nouveau filtre, des codes — n’est pas recherché. Une reconstruction remplit de nouvelles tables à côté des tables en service puis les échange ; sur un gros catalogue, ce sont des minutes de charge, choisissez donc le moment.',
    'rebuild' => 'Reconstruire',
    'rebuild-again' => 'Reconstruire à nouveau',
    'rebuild-title' => 'Reconstruire l’index de recherche ?',
    'rebuild-text' => 'Chaque produit est réécrit dans de nouvelles tables, qui remplacent ensuite les tables en service. En attendant, la vitrine continue de fonctionner sur les anciennes tables.',
    'rebuild-busy' => 'Une reconstruction est déjà en cours.',
    'rebuild-locked' => 'Une autre reconstruction tourne — elle a été lancée depuis la console. Lancez celle-ci quand elle sera terminée.',
    'rebuild-console' => 'Une reconstruction lancée depuis la console est en cours : le bouton revient quand elle se termine.',
    'rebuild-queued' => 'En attente du worker de la file',
    'rebuild-running' => 'Reconstruction : :done sur :total',
    'rebuild-done' => 'Reconstruit : :done produits',
    'rebuild-failed' => 'La reconstruction a échoué',
    'rebuild-stalled' => 'Aucune nouvelle de la reconstruction depuis un quart d’heure : le worker de la file ne tourne pas ou s’est arrêté. Relancez-la une fois le worker en marche.',
    'rebuild-started' => 'La reconstruction est dans la file.',
    'cancel' => 'Annuler',
];
