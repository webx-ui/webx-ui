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

    /**
     * The panel permissions any one of which lets an administrator use this tool.
     *
     * Derived from the module id the way the scope is, and the way the module's own routes
     * guard the same work: a mutating tool is behind `pages.manage`, a read tool behind
     * `pages.view` — or `pages.manage`, because somebody who may edit pages may look at them,
     * and the panel lets them without a separate `view`. A module whose permissions are not
     * named after it says so on the tool.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        if ($this->tool->permissions !== null) {
            return $this->tool->permissions;
        }

        return $this->tool->mutating
            ? [$this->moduleId.'.manage']
            : [$this->moduleId.'.view', $this->moduleId.'.manage'];
    }
}
