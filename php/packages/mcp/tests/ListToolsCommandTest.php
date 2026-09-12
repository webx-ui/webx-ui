<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Mcp\Tests\Fixtures\MediaLibraryModule;
use WebxUi\Mcp\Tests\Fixtures\SeoModule;

final class ListToolsCommandTest extends TestCase
{
    #[Test]
    public function it_lists_what_the_panel_offers_an_agent(): void
    {
        $this->register(new SeoModule, new MediaLibraryModule);

        $this->artisan('webx:mcp-tools')
            ->expectsOutputToContain('seo_bulk_update_seo')
            ->expectsOutputToContain('media_library_find_unused')
            ->expectsOutputToContain('media:audit')
            ->assertSuccessful();
    }

    #[Test]
    public function it_can_be_narrowed_to_one_module(): void
    {
        $this->register(new SeoModule, new MediaLibraryModule);

        $this->artisan('webx:mcp-tools', ['--module' => 'seo'])
            ->expectsOutputToContain('seo_get_seo')
            ->doesntExpectOutputToContain('media_library_find_unused')
            ->assertSuccessful();
    }

    #[Test]
    public function it_says_so_when_there_is_nothing(): void
    {
        $this->artisan('webx:mcp-tools')
            ->expectsOutputToContain('No installed module offers MCP tools yet.')
            ->assertSuccessful();
    }

    #[Test]
    public function it_says_so_when_the_named_module_offers_nothing(): void
    {
        $this->register(new SeoModule);

        $this->artisan('webx:mcp-tools', ['--module' => 'pages'])
            ->expectsOutputToContain('Module [pages] offers no MCP tools.')
            ->assertSuccessful();
    }
}
