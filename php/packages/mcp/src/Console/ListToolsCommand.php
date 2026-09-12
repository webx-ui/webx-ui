<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Console;

use Illuminate\Console\Command;
use WebxUi\Mcp\Registry\BoundTool;
use WebxUi\Mcp\Registry\ToolRegistry;

final class ListToolsCommand extends Command
{
    protected $signature = 'webx:mcp-tools {--module= : Only the tools of this module}';

    protected $description = 'List the MCP tools the installed modules offer';

    public function handle(ToolRegistry $registry): int
    {
        $module = $this->option('module');
        $tools = is_string($module) && $module !== ''
            ? $registry->toolsOf($module)
            : $registry->tools();

        if ($tools === []) {
            $this->components->warn(
                is_string($module) && $module !== ''
                    ? "Module [{$module}] offers no MCP tools."
                    : 'No installed module offers MCP tools yet.'
            );

            return self::SUCCESS;
        }

        $this->table(
            ['Tool', 'Module', 'Scope', 'Changes data', 'Description'],
            array_map(static fn (BoundTool $tool): array => [
                $tool->fullName(),
                $tool->moduleId,
                $tool->scope(),
                $tool->tool->mutating ? 'yes' : 'no',
                $tool->tool->description,
            ], $tools),
        );

        $this->components->twoColumnDetail('Tools', (string) count($tools));
        $this->components->twoColumnDetail('Scopes', implode(', ', $registry->scopes()));

        return self::SUCCESS;
    }
}
