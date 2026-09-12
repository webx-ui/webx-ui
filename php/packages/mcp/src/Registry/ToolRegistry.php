<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Registry;

use WebxUi\Admin\Contracts\Module;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\Exceptions\McpException;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Prompt;

/**
 * Everything the installed modules offer an agent, in one place.
 *
 * Nothing is registered here directly: the source of truth is the module registry, so a panel
 * exposes exactly the tools of the modules it actually has.
 */
final class ToolRegistry
{
    public function __construct(private readonly ModuleRegistry $modules) {}

    /**
     * @return list<BoundTool>
     */
    public function tools(): array
    {
        $tools = [];
        $seen = [];

        foreach ($this->providers() as $module) {
            foreach ($module->mcpTools() as $tool) {
                $bound = new BoundTool($module->id(), $tool);
                $name = $bound->fullName();

                if (isset($seen[$name])) {
                    throw McpException::duplicateTool($name);
                }

                $seen[$name] = true;
                $tools[] = $bound;
            }
        }

        return $tools;
    }

    public function tool(string $fullName): BoundTool
    {
        foreach ($this->tools() as $tool) {
            if ($tool->fullName() === $fullName) {
                return $tool;
            }
        }

        throw McpException::unknownTool($fullName);
    }

    /**
     * @return list<BoundTool>
     */
    public function toolsOf(string $moduleId): array
    {
        return array_values(array_filter(
            $this->tools(),
            static fn (BoundTool $tool): bool => $tool->moduleId === $moduleId,
        ));
    }

    /**
     * @return list<McpResource>
     */
    public function resources(): array
    {
        return array_merge(...array_map(
            static fn (ProvidesMcpTools $module): array => $module->mcpResources(),
            $this->providers(),
        ) ?: [[]]);
    }

    /**
     * @return list<Prompt>
     */
    public function prompts(): array
    {
        return array_merge(...array_map(
            static fn (ProvidesMcpTools $module): array => $module->mcpPrompts(),
            $this->providers(),
        ) ?: [[]]);
    }

    /**
     * Scopes a token needs to reach everything on offer.
     *
     * @return list<string>
     */
    public function scopes(): array
    {
        $scopes = array_map(static fn (BoundTool $tool): string => $tool->scope(), $this->tools());

        $scopes = array_values(array_unique($scopes));
        sort($scopes);

        return $scopes;
    }

    /**
     * Modules that speak MCP. A module that does not is not an error — it simply offers an
     * agent nothing yet.
     *
     * @return list<ProvidesMcpTools&Module>
     */
    private function providers(): array
    {
        return array_values(array_filter(
            $this->modules->all(),
            static fn ($module): bool => $module instanceof ProvidesMcpTools,
        ));
    }
}
