<?php

declare(strict_types=1);

namespace WebxUi\Themes\Exceptions;

use RuntimeException;

/**
 * A theme that cannot be loaded: missing, unreadable, or a chain that loops. Thrown at boot on
 * purpose — a site that quietly drops a layer renders half-styled, and nobody finds out why.
 */
class ThemeException extends RuntimeException
{
    public static function notFound(string $reference, ?string $usedBy = null): self
    {
        return new self($usedBy === null
            ? "Theme [{$reference}] was not found: neither an installed package nor a directory with theme.json."
            : "Theme [{$reference}], used by [{$usedBy}], was not found: neither an installed package nor a directory with theme.json.");
    }

    public static function invalidManifest(string $path, string $reason): self
    {
        return new self("Theme manifest [{$path}] is invalid: {$reason}");
    }

    /**
     * @param  list<string>  $path  The names from the theme that started the loop back to itself.
     */
    public static function cycle(array $path): self
    {
        return new self('Themes use each other in a loop: '.implode(' -> ', $path).'.');
    }
}
