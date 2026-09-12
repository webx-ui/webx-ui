<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Exceptions;

use RuntimeException;

class McpException extends RuntimeException
{
    public static function invalidName(string $name): self
    {
        return new self(
            "[{$name}] is not a usable tool name. Use lowercase letters, digits and underscores, "
            .'starting with a letter — the name reaches the model verbatim.'
        );
    }

    public static function emptyDescription(string $name): self
    {
        return new self(
            "Tool [{$name}] has no description. The description is how a model decides whether "
            .'to call it, so an empty one makes the tool useless rather than merely undocumented.'
        );
    }

    public static function unknownTool(string $name): self
    {
        return new self("No tool is registered under the name [{$name}].");
    }

    public static function duplicateTool(string $name): self
    {
        return new self("Two modules both offer a tool named [{$name}].");
    }
}
