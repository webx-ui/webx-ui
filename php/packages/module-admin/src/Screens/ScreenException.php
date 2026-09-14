<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens;

use RuntimeException;

/**
 * A broken description is a bug, not a mode of operation: every problem with a screen or a
 * patch is thrown, at registration where possible and at first use otherwise.
 */
class ScreenException extends RuntimeException
{
    public static function badName(string $name): self
    {
        return new self("A screen is named <module>.<screen>, in lowercase; [{$name}] is not.");
    }

    public static function duplicate(string $name): self
    {
        return new self("The screen [{$name}] is already registered.");
    }

    public static function unknown(string $name): self
    {
        return new self("No screen named [{$name}] is registered.");
    }

    public static function unreadable(string $path): self
    {
        return new self("The screen file [{$path}] cannot be read as JSON.");
    }

    /**
     * @param  list<string>  $problems
     */
    public static function invalid(string $what, array $problems): self
    {
        return new self("{$what} is not a valid description:\n - ".implode("\n - ", $problems));
    }

    public static function patchFailed(string $screen, int $index, string $op, string $message): self
    {
        return new self("Patch [{$index}] ({$op}) on [{$screen}] cannot be applied: {$message}.");
    }
}
