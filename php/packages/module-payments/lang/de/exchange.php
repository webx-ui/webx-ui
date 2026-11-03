<?php

declare(strict_types=1);

// The journal of the exchange with gateways (spec §20): directions, kinds, signatures, outcomes.
return [
    'title' => 'Austausch mit dem Gateway',
    'directions' => [
        'out' => 'An das Gateway',
        'in' => 'Vom Gateway',
        'browser' => 'Über den Browser des Käufers',
        'internal' => 'Entscheidung der Website',
    ],
    'kinds' => [
        'start' => 'Zahlung eröffnet',
        'status' => 'Statusabfrage',
        'cancel' => 'Stornierung',
        'complete' => 'Freigabe abgeschlossen',
        'capture' => 'Einzugsanfrage',
        'void' => 'Freigabeanfrage',
        'connection' => 'Verbindungstest',
        'key' => 'Signaturschlüssel',
        'other' => 'Andere Anfrage',
        'notify' => 'Benachrichtigung',
        'handoff' => 'Zum Gateway geschickt',
        'return' => 'Zurück vom Gateway',
        'expire' => 'Abgelaufen',
        'manual' => 'Manuell markiert',
        'captured' => 'Eingezogen',
        'released' => 'Reservierung freigegeben',
    ],
    'signature' => [
        'valid' => 'Signatur gültig',
        'invalid' => 'Signatur ungültig',
        'untrusted' => 'Nicht signiert — Status beim Gateway abgefragt',
        'none' => 'Nicht geprüft',
    ],
    'outcomes' => [
        'applied' => 'Übernommen',
        'same' => 'Nichts Neues',
        'ignored' => 'Ignoriert',
        'duplicate' => 'Wiederholung',
        'rejected' => 'Abgelehnt',
        'unmatched' => 'Keine solche Zahlung',
        'error' => 'Fehler',
    ],
    'truncated' => 'Am Limit gekürzt',
    'download' => 'Herunterladen',
    'size' => 'Größe',
    'masked' => 'Geheimnisse verborgen',
];
