<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Mcp;

use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\McpResource;
use WebxUi\Tasks\Models\Project;
use WebxUi\Tasks\Workflow\Present;

/**
 * What an agent reads before it works (§5, MCP resources): the rules of the process, the same for
 * every site, and one project with its own rules.
 */
final class TasksResources
{
    public const string RULES = 'tasks://rules';

    public const string PROJECT = 'tasks://projects/{slug}';

    /**
     * @return list<McpResource>
     */
    public function all(): array
    {
        return [
            new McpResource(
                self::RULES,
                'Rules for agents',
                'How to take a stage, what to do before reporting, the report format, what is never done, '
                .'and what to do when stuck. Read before the first stage.',
                static fn (): string => (string) file_get_contents(__DIR__.'/../../resources/mcp/rules.md'),
                'text/markdown',
            ),
            new McpResource(
                self::PROJECT,
                'Project',
                'One project by its slug: the folder and repository agents work in, the default branch, and the '
                .'project\'s own rules.',
                static function (array $arguments): array {
                    $project = Project::query()->where('slug', (string) ($arguments['slug'] ?? ''))->first();

                    if (! $project instanceof Project) {
                        throw new ToolFailure('No such project.');
                    }

                    return ['project' => Present::project($project)];
                },
            ),
        ];
    }
}
