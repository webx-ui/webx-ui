<?php

declare(strict_types=1);

namespace WebxUi\NestedSet\Tests\Fixtures;

/**
 * The same table with `SoftDeletes` and nothing said about the tree: the default, which refuses
 * the delete rather than leaving a hole behind.
 */
class StrictNote extends Note
{
    public function softDeletesInTree(): bool
    {
        return false;
    }
}
