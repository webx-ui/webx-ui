<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Registry;

use WebxUi\Mcp\Tool;

/**
 * A module's tool once the server knows which module it came from.
 *
 * The full name is what reaches the model, so it has to be unique across the whole panel and
 * has to survive the character rules agents impose: letters, digits and underscores only.
 */
final class BoundTool
{
    public function __construct(
        public readonly string $moduleId,
        public readonly Tool $tool,
    ) {}

    public function fullName(): string
    {
        return str_replace('-', '_', $this->moduleId).'_'.$this->tool->name;
    }

    /** `pages:write` for a mutating tool, `pages:read` otherwise, unless the tool says so itself. */
    public function scope(): string
    {
        return $this->tool->scope ?? $this->moduleId.':'.($this->tool->mutating ? 'write' : 'read');
    }
}
