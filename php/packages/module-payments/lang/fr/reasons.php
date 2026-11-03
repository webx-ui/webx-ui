<?php

declare(strict_types=1);

// Why a method is not offered (spec §7.2): for the manager and the agent, never the buyer.
return [
    'nothing_to_pay' => 'Rien à payer',
    'type_missing' => 'Le type :type n\'est pas installé',
    'currency' => 'Ce mode n\'accepte pas :currency',
    'amount_below_min' => 'Le montant est inférieur au minimum',
    'amount_above_max' => 'Le montant dépasse le maximum',
    'delivery_pending' => 'Attend le choix de la livraison',
    'delivery_rule' => 'Indisponible pour la livraison choisie',
    'no_credentials' => 'Les clés de la passerelle ne sont pas définies',
    'gateway_missing' => 'La passerelle :gateway n’est pas installée',
];
