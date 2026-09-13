<?php

declare(strict_types=1);

namespace WebxUi\Admin\Exceptions;

use RuntimeException;

class ModuleException extends RuntimeException
{
    public static function duplicate(string $id): self
    {
        return new self("A module with the id [{$id}] is already registered.");
    }

    public static function unknown(string $id): self
    {
        return new self("No module is registered under the id [{$id}].");
    }

    public static function emptyId(string $class): self
    {
        return new self("Module [{$class}] returned an empty id.");
    }
}
