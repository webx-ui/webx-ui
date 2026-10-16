<?php

declare(strict_types=1);

return [
    'name' => 'Nom',
    'name-help' => 'Réservé aux administrateurs.',
    'provider' => 'Fournisseur',
    'provider-help' => 'Choisi une seule fois : un canal d\'un autre type est un nouveau canal.',
    'event' => 'Pour l\'événement',
    'event-help' => 'Avec un événement, l\'éditeur propose et vérifie ses variables, et seul cet événement propose le canal. Sans événement, le canal sert à tous les événements.',
    'any-event' => 'Tout événement',
    'enabled' => 'Activé',
    'enabled-help' => 'Un canal désactivé n\'envoie rien d\'autre que des tests.',
    'connection' => 'Connexion',
    'message' => 'Message',
    'subject' => 'Objet',
    'subject-help' => 'Une ligne ; les variables comme {{form}} sont remplacées.',
    'template' => 'Modèle',
    'template-help' => 'Variables entre doubles accolades : {{form}}. Une seule langue suffit ; un message dans une langue absente du modèle utilise la langue principale du site.',
    'history' => 'Historique',
    'token-help' => 'Fourni par @BotFather. Stocké chiffré et plus jamais affiché : saisissez-en un nouveau pour le remplacer.',
    'chats' => 'Chats',
    'chats-help' => 'Identifiants de chat, ou « Trouver des chats » après avoir écrit au bot. Un destinataire issu de l\'événement est l\'adresse qu\'apporte l\'événement.',
    'mode-help' => 'La messagerie du site est celle configurée pour l\'ensemble du site.',
    'from-help' => 'Vide — l\'expéditeur du site.',
    'emails' => 'Destinataires',
    'emails-help' => 'Administrateurs, adresses, ou l\'adresse apportée par l\'événement — le visiteur qui a rempli le formulaire.',
    'credentials' => 'Secrets',
    'options' => 'Paramètres',
    'recipients' => 'Destinataires',
];
