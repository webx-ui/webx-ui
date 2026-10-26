<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Engine;

use RuntimeException;

/**
 * A formula that is not one: where it went wrong, counted in bytes from 0, and what was there.
 */
final class FormulaError extends RuntimeException
{
    public function __construct(public readonly int $position, public readonly string $reason)
    {
        parent::__construct("{$reason} at {$position}");
    }
}
