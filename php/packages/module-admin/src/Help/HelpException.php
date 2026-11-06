<?php

declare(strict_types=1);

namespace WebxUi\Admin\Help;

use RuntimeException;

/**
 * A file of help that cannot be taken apart. Like a broken screen it is a bug in a package, not
 * a mode of operation, so it names the file instead of showing half a page.
 */
final class HelpException extends RuntimeException
{
    public static function unreadable(string $path): self
    {
        return new self("The help file [{$path}] cannot be read.");
    }

    public static function invalid(string $path, string $problem): self
    {
        return new self("The help file [{$path}] is not valid: {$problem}.");
    }
}
