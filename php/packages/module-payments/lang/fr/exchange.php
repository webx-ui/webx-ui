<?php

declare(strict_types=1);

// The journal of the exchange with gateways (spec §20): directions, kinds, signatures, outcomes.
return [
    'title' => 'Échanges avec la passerelle',
    'directions' => [
        'out' => 'Vers la passerelle',
        'in' => 'De la passerelle',
        'browser' => 'Via le navigateur de l\'acheteur',
        'internal' => 'Décision du site',
    ],
    'kinds' => [
        'start' => 'Paiement ouvert',
        'status' => 'Demande de statut',
        'cancel' => 'Annulation',
        'complete' => 'Approbation finalisée',
        'capture' => 'Demande de capture',
        'void' => 'Demande de libération',
        'connection' => 'Test de connexion',
        'key' => 'Clé de signature',
        'other' => 'Autre requête',
        'notify' => 'Notification',
        'handoff' => 'Envoyé à la passerelle',
        'return' => 'Retour de la passerelle',
        'expire' => 'Expiré',
        'manual' => 'Marqué à la main',
        'captured' => 'Capturé',
        'released' => 'Préautorisation libérée',
    ],
    'signature' => [
        'valid' => 'Signature valide',
        'invalid' => 'Signature invalide',
        'untrusted' => 'Non signé — statut demandé à la passerelle',
        'none' => 'Non vérifiée',
    ],
    'outcomes' => [
        'applied' => 'Appliqué',
        'same' => 'Rien de nouveau',
        'ignored' => 'Ignoré',
        'duplicate' => 'Répétition',
        'rejected' => 'Rejeté',
        'unmatched' => 'Paiement introuvable',
        'error' => 'Erreur',
    ],
    'truncated' => 'Coupé à la limite',
    'download' => 'Télécharger',
    'size' => 'Taille',
    'masked' => 'Secrets masqués',
];
