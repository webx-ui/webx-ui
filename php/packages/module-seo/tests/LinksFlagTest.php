<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Mcp\Tool;
use WebxUi\Seo\Models\SeoLinkBlock;
use WebxUi\Seo\Models\SeoLinkItem;
use WebxUi\Seo\Panel\SeoModule;
use WebxUi\Seo\Sitemap\SitemapRoutes;

/**
 * Off by default, and off means gone (§18.1, decision 2): no routes, no tools, a false flag in the
 * manifest, a component that prints nothing — and the tables still there, data and all.
 */
final class LinksFlagTest extends TestCase
{
    #[Test]
    public function a_feature_that_is_off_has_no_routes(): void
    {
        $this->actingAs($this->editor(), 'cms')->getJson($this->api('links'))->assertNotFound();
        // A POST nobody registered meets the registry's fallback, which takes GET only: 405 is
        // the router saying the same thing as the 404 above.
        $status = $this->actingAs($this->editor(), 'cms')->postJson($this->api('links/import'))->getStatusCode();
        $this->assertContains($status, [404, 405]);
    }

    #[Test]
    public function a_feature_that_is_off_has_no_tools(): void
    {
        $names = array_map(static fn (Tool $tool): string => $tool->name, app(SeoModule::class)->mcpTools());

        $this->assertContains('urls_list', $names);
        $this->assertSame([], array_values(array_filter($names, static fn (string $name): bool => str_starts_with($name, 'links_'))));
    }

    #[Test]
    public function the_manifest_says_which_features_are_on(): void
    {
        $modules = $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/manifest')->assertOk()->json('data.modules');
        $seo = array_values(array_filter((array) $modules, static fn (mixed $module): bool => is_array($module) && ($module['id'] ?? null) === 'seo'))[0] ?? null;

        $this->assertIsArray($seo);

        $this->assertSame(['links' => false, 'faq' => false], $seo['meta']);
    }

    #[Test]
    public function the_tables_are_there_and_the_component_is_silent_until_the_feature_is_turned_on(): void
    {
        $this->assertTrue(Schema::hasTable('seo_link_blocks'));

        app(SitemapRoutes::class)->register('webx.seo.robots');

        $block = SeoLinkBlock::query()->create(['locale' => 'ru', 'path' => '/', 'heading' => 'Read on']);
        SeoLinkItem::query()->create(['block_id' => $block->id, 'locale' => 'ru', 'path' => '/robots.txt', 'anchor' => 'Robots', 'position' => 0]);

        $this->assertSame('', trim(Blade::render('<x-webx-seo::links />')));

        // Turned on again, what was written is where it was.
        config()->set('webx-seo.links.enabled', true);

        $html = Blade::render('<x-webx-seo::links />');

        $this->assertStringContainsString('Read on', $html);
        $this->assertStringContainsString('href="/robots.txt"', $html);
    }

    private function api(string $path): string
    {
        return '/api/cms/seo/'.$path;
    }
}
