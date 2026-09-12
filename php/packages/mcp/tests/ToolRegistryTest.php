<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\AbstractModule;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\Exceptions\McpException;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tests\Fixtures\MediaLibraryModule;
use WebxUi\Mcp\Tests\Fixtures\SeoModule;
use WebxUi\Mcp\Tool;

final class ToolRegistryTest extends TestCase
{
    #[Test]
    public function it_collects_the_tools_of_every_module_that_speaks_mcp(): void
    {
        $this->register(new SeoModule, new MediaLibraryModule);

        $this->assertSame(
            ['media_library_find_unused', 'seo_get_seo', 'seo_bulk_update_seo'],
            array_map(fn ($tool) => $tool->fullName(), $this->tools()->tools()),
        );
    }

    #[Test]
    public function a_module_without_mcp_is_simply_quiet(): void
    {
        $this->register(new class extends AbstractModule
        {
            public function id(): string
            {
                return 'plain';
            }
        });

        $this->assertSame([], $this->tools()->tools());
        $this->assertSame([], $this->tools()->scopes());
    }

    #[Test]
    public function scopes_default_to_read_and_write_per_module(): void
    {
        $this->register(new SeoModule, new MediaLibraryModule);

        $this->assertSame(['media:audit', 'seo:read', 'seo:write'], $this->tools()->scopes());
    }

    #[Test]
    public function a_tool_can_be_found_by_its_full_name(): void
    {
        $this->register(new SeoModule);

        $tool = $this->tools()->tool('seo_bulk_update_seo');

        $this->assertSame('seo', $tool->moduleId);
        $this->assertTrue($tool->tool->mutating);
    }

    #[Test]
    public function an_unknown_tool_is_an_error(): void
    {
        $this->expectException(McpException::class);
        $this->expectExceptionMessage('[seo_nope]');

        $this->tools()->tool('seo_nope');
    }

    #[Test]
    public function two_modules_cannot_offer_the_same_full_name(): void
    {
        // Ids differing only by a dash collapse to the same prefix once the name is made
        // agent-safe, which would otherwise silently shadow one module's tool.
        $this->register(
            new class extends AbstractModule implements ProvidesMcpTools
            {
                use ProvidesMcpDefaults;

                public function id(): string
                {
                    return 'media-library';
                }

                public function mcpTools(): array
                {
                    return [Tool::read('find_unused', 'One.', static fn (): array => [])];
                }
            },
            new class extends AbstractModule implements ProvidesMcpTools
            {
                use ProvidesMcpDefaults;

                public function id(): string
                {
                    return 'media_library';
                }

                public function mcpTools(): array
                {
                    return [Tool::read('find_unused', 'Two.', static fn (): array => [])];
                }
            },
        );

        $this->expectException(McpException::class);
        $this->expectExceptionMessage('[media_library_find_unused]');

        $this->tools()->tools();
    }

    #[Test]
    public function resources_and_prompts_come_through_too(): void
    {
        $this->register(new SeoModule, new MediaLibraryModule);

        $this->assertSame(
            ['seo://audit/missing-title'],
            array_map(fn ($resource) => $resource->uri, $this->tools()->resources()),
        );

        $this->assertSame(
            ['suggest_titles'],
            array_map(fn ($prompt) => $prompt->name, $this->tools()->prompts()),
        );
    }

    #[Test]
    public function nothing_registered_means_empty_lists_rather_than_an_error(): void
    {
        $this->assertSame([], $this->tools()->tools());
        $this->assertSame([], $this->tools()->resources());
        $this->assertSame([], $this->tools()->prompts());
    }

    #[Test]
    public function tools_can_be_asked_for_one_module_at_a_time(): void
    {
        $this->register(new SeoModule, new MediaLibraryModule);

        $this->assertCount(2, $this->tools()->toolsOf('seo'));
        $this->assertCount(1, $this->tools()->toolsOf('media-library'));
        $this->assertCount(0, $this->tools()->toolsOf('nope'));
    }
}
