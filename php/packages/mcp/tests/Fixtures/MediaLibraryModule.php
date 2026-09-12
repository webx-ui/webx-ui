<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Tests\Fixtures;

use WebxUi\Admin\AbstractModule;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;

/**
 * A dash in the id, and nothing to say about resources or prompts.
 */
final class MediaLibraryModule extends AbstractModule implements ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public function id(): string
    {
        return 'media-library';
    }

    /**
     * @return list<Tool>
     */
    public function mcpTools(): array
    {
        return [
            Tool::read(
                'find_unused',
                'List files no entity refers to.',
                static fn (): array => [],
                scope: 'media:audit',
            ),
        ];
    }
}
