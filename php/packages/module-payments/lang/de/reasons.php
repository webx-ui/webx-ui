<?php

declare(strict_types=1);

// Why a method is not offered (spec §7.2): for the manager and the agent, never the buyer.
return [
    'nothing_to_pay' => 'Nichts zu bezahlen',
    'type_missing' => 'Der Typ :type ist nicht installiert',
    'currency' => 'Die Zahlungsart akzeptiert kein :currency',
    'amount_below_min' => 'Der Betrag liegt unter dem Minimum',
    'amount_above_max' => 'Der Betrag liegt über dem Maximum',
    'delivery_pending' => 'Wartet auf die Wahl des Versands',
    'delivery_rule' => 'Für den gewählten Versand nicht verfügbar',
    'no_credentials' => 'Die Schlüssel des Gateways fehlen',
    'gateway_missing' => 'Das Gateway :gateway ist nicht installiert',
];
