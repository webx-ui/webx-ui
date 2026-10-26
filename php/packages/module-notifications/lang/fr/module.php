<?php

declare(strict_types=1);

return [
    'title' => 'Notifications',
    'channel' => 'Canal',
    'copy-name' => ':name (copie)',
    'unsaved' => 'Canal non enregistré',
    'permissions' => [
        'view' => 'Voir les canaux et le journal des envois, y compris ce que les visiteurs ont envoyé via les formulaires.',
        'manage' => 'Configurer les canaux et leurs secrets, envoyer des tests, rechercher des chats et renvoyer les notifications échouées.',
    ],
    'statuses' => [
        'queued' => 'En file d\'attente',
        'sent' => 'Envoyé',
        'trapped' => 'Transport de test',
        'failed' => 'Échec',
        'skipped' => 'Ignoré',
    ],
    'reasons' => [
        'channel_deleted' => 'Le canal est supprimé',
        'channel_disabled' => 'Le canal est désactivé',
        'wrong_event' => 'Le canal est destiné à un autre événement',
        'provider_missing' => 'Le fournisseur n\'est pas installé',
        'no_recipients' => 'Le canal n\'a aucun destinataire',
        'no_address' => 'L\'événement n\'a apporté aucune adresse',
        'admin_deleted' => 'L\'administrateur est supprimé',
        'admin_inactive' => 'L\'administrateur est désactivé',
    ],
];
