<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Manifest\ManifestBuilder;
use WebxUi\Catalog\Manticore\ManticoreServiceProvider;
use WebxUi\Catalog\Tests\TestCase;
use WebxUi\Mcp\Registry\BoundTool;
use WebxUi\Mcp\Registry\ToolRegistry;

/**
 * The package installed on a site whose engine is the database: no «Search index» in the menu, no
 * `catalog_index_status`, and the page answers 404 — there is no index to tell about.
 */
final class SearchIndexOnTheDatabaseTest extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), ManticoreServiceProvider::class];
    }

    #[Test]
    public function neither_the_section_nor_the_tool_is_there(): void
    {
        $manifest = $this->app->make(ManifestBuilder::class)->build();

        $this->assertNull(collect($manifest['modules'])->firstWhere('id', 'search-index'));
        $this->assertNotContains('catalog_index_status', array_map(
            static fn (BoundTool $tool): string => $tool->fullName(),
            $this->app->make(ToolRegistry::class)->toolsOf('catalog'),
        ));
        $this->actingAs($this->editor(['search-index.view']), 'cms')->getJson('/api/cms/search-index')->assertNotFound();
    }
}
