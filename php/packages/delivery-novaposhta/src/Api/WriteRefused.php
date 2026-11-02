<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Api;

use LogicException;

/**
 * A method was refused before any request: a writing one in read-only mode, or one the client
 * does not know at all ({@see Methods}). A bug or a guard, never the carrier's answer.
 */
final class WriteRefused extends LogicException
{
    public static function readOnly(string $method): self
    {
        return new self("Nova Poshta: {$method} changes the carrier's account and the client is read only (webx-delivery-novaposhta.read_only).");
    }

    public static function unknown(string $method): self
    {
        return new self("Nova Poshta: {$method} is neither a reading nor a writing method of the client; add it to Api\\Methods first.");
    }
}
