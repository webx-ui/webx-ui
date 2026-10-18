<?php

declare(strict_types=1);

namespace WebxUi\Auth\Keys;

use RuntimeException;

/** Why a key cannot be issued, in words that name the cure — and the field of the form at fault. */
final class KeyRefused extends RuntimeException
{
    public function __construct(string $message, public readonly string $field = 'admin')
    {
        parent::__construct($message);
    }
}
