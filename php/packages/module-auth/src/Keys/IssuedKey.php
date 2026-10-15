<?php

declare(strict_types=1);

namespace WebxUi\Auth\Keys;

use WebxUi\Mcp\Keys\Key;

/**
 * A key just issued, with its secret — the one moment the secret exists outside the program
 * that will hold it. Nothing stores it; whoever issued the key copies it now or issues another.
 */
final readonly class IssuedKey
{
    public function __construct(
        public Key $key,
        public string $token,
    ) {}
}
