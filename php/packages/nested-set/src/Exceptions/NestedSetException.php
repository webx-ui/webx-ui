<?php

declare(strict_types=1);

namespace WebxUi\NestedSet\Exceptions;

use RuntimeException;

class NestedSetException extends RuntimeException
{
    public static function movingIntoOwnSubtree(): self
    {
        return new self('A node cannot be moved into itself or into its own subtree.');
    }

    public static function targetMissing(): self
    {
        return new self('The target node no longer exists.');
    }

    public static function targetNotSaved(): self
    {
        return new self('The target node has to be saved before another node can be placed relative to it.');
    }

    public static function differentScope(string $attribute): self
    {
        return new self("Nodes belong to different trees: the scope attribute [{$attribute}] does not match.");
    }

    public static function softDeleteUnsupported(): self
    {
        return new self(
            'Soft deleting a nested set node would leave a hole in the tree. '
            .'Use forceDelete(), or drop SoftDeletes from the model.'
        );
    }
}
