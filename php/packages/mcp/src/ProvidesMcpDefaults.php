<?php

declare(strict_types=1);

namespace WebxUi\Mcp;

/**
 * Empty answers for the parts of {@see Contracts\ProvidesMcpTools} a module does not use, so
 * that offering three tools and nothing else stays three methods short.
 */
trait ProvidesMcpDefaults
{
    /**
     * @return list<McpResource>
     */
    public function mcpResources(): array
    {
        return [];
    }

    /**
     * @return list<Prompt>
     */
    public function mcpPrompts(): array
    {
        return [];
    }
}
