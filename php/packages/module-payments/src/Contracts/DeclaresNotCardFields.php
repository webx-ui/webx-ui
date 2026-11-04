<?php

declare(strict_types=1);

namespace WebxUi\Payments\Contracts;

/**
 * A gateway whose answers carry long numbers that are not cards — a date in milliseconds, a payment
 * id — and pass Luhn by chance (spec §6.4, §20.5). Optional: without it every number is looked at.
 */
interface DeclaresNotCardFields extends Gateway
{
    /**
     * Names of json and form fields the card rule of the journal leaves alone (§20.5, rule 4). Only
     * the value of such a field in a body: the same number anywhere else is still starred, and a
     * secret is wiped from these fields like from any other.
     *
     * @return list<string>
     */
    public function notCardFields(): array;
}
