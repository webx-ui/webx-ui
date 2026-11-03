<?php

declare(strict_types=1);

// Why a method is not offered (spec §7.2): for the manager and the agent, never the buyer.
return [
    'nothing_to_pay' => 'Nic do zapłaty',
    'type_missing' => 'Typ :type nie jest zainstalowany',
    'currency' => 'Metoda nie przyjmuje :currency',
    'amount_below_min' => 'Kwota jest poniżej minimum metody',
    'amount_above_max' => 'Kwota przekracza maksimum metody',
    'delivery_pending' => 'Czeka na wybór dostawy',
    'delivery_rule' => 'Niedostępna dla wybranej dostawy',
    'no_credentials' => 'Klucze bramki nie są ustawione',
    'gateway_missing' => 'Bramka :gateway nie jest zainstalowana',
];
