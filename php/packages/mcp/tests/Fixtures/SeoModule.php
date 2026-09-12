<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Tests\Fixtures;

use WebxUi\Admin\AbstractModule;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Prompt;
use WebxUi\Mcp\Tool;

final class SeoModule extends AbstractModule implements ProvidesMcpTools
{
    public function id(): string
    {
        return 'seo';
    }

    /**
     * @return list<Tool>
     */
    public function mcpTools(): array
    {
        return [
            Tool::read(
                'get_seo',
                'Read the SEO fields of one entity.',
                static fn (array $arguments): array => ['title' => 'Example', 'for' => $arguments['id'] ?? null],
                [
                    'properties' => ['id' => ['type' => 'string']],
                    'required' => ['id'],
                ],
            ),
            Tool::mutating(
                'bulk_update_seo',
                'Apply a title template to every entity matching a filter.',
                static fn (array $arguments): array => ['changed' => $arguments['dry_run'] ?? false ? 0 : 12],
                ['properties' => ['template' => ['type' => 'string']]],
            ),
        ];
    }

    /**
     * @return list<McpResource>
     */
    public function mcpResources(): array
    {
        return [
            new McpResource(
                'seo://audit/missing-title',
                'Entities without a title',
                'Everything that would render with an empty <title>.',
                static fn (): array => [],
            ),
        ];
    }

    /**
     * @return list<Prompt>
     */
    public function mcpPrompts(): array
    {
        return [
            new Prompt(
                'suggest_titles',
                'Draft titles for entities that have none.',
                static fn (): string => 'Draft a title for each of these pages.',
                ['section' => 'Section to work through'],
            ),
        ];
    }
}
