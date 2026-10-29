<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout\Steps;

/**
 * A step (or a slot) that says which `StepError::code`s it may answer (spec §32): `orders://checkout`
 * lists them, so an agent reads a code it meets instead of guessing. Optional — a step without it
 * is listed with its codes unknown.
 */
interface NamesErrors
{
    /** @return array<string, string> the code and, in English, when it comes */
    public function errorCodes(): array;
}
