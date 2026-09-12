<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Contracts;

use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Prompt;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;

/**
 * What a module offers an AI agent.
 *
 * Every WebX UI module implements this. CRUD is the floor, not the goal: the useful tools are
 * the ones that answer a question a person would otherwise answer by clicking for an hour —
 * bulk edits, audits, "what changed this week". Use {@see ProvidesMcpDefaults} to
 * skip the parts a module has nothing to say about.
 */
interface ProvidesMcpTools
{
    /**
     * @return list<Tool>
     */
    public function mcpTools(): array;

    /**
     * @return list<McpResource>
     */
    public function mcpResources(): array;

    /**
     * @return list<Prompt>
     */
    public function mcpPrompts(): array;
}
