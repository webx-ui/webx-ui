<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\View\DynamicComponent;
use ReflectionProperty;
use WebxUi\Blocks\Models\Region;
use WebxUi\Blocks\Tests\Fixtures\RegionPage;
use WebxUi\Blocks\Tests\Fixtures\RegionPageHandler;
use WebxUi\Routing\Formatters\Slug;
use WebxUi\Routing\RouteType;
use WebxUi\Routing\RouteTypes;

/**
 * A site with two regions in its layout: `header`, with the header from code as its fallback, and
 * `footer`, with none. Pages of the `landing` type stand in that layout.
 */
abstract class RegionTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::forgetDynamicComponents();

        // Per test and under a prefix of its own (CLAUDE.md §4 on `<x-dynamic-component>`).
        Blade::anonymousComponentPath(__DIR__.'/Fixtures/regions', 'region-site');
        View::addNamespace('region-site', __DIR__.'/Fixtures/regions');

        $this->app['config']->set('webx-blocks.regions', [
            'header' => ['title' => 'trans::webx-blocks::regions.header', 'description' => 'Top of every page.'],
            'footer' => ['title' => 'trans::webx-blocks::regions.footer', 'max' => 2],
        ]);

        $this->app->make(RouteTypes::class)->register(new RouteType(
            type: 'landing',
            model: RegionPage::class,
            formatter: Slug::class,
            handler: RegionPageHandler::class,
        ));
    }

    protected function tearDown(): void
    {
        self::forgetDynamicComponents();

        parent::tearDown();
    }

    /**
     * A region with its tree published, the way the panel leaves it.
     *
     * @param  list<array<string, mixed>>  $blocks
     */
    protected function region(string $name, array $blocks, bool $published = true): Region
    {
        $region = Region::query()->firstOrNew(['name' => $name]);
        $region->saveDraft(['blocks' => $blocks]);

        if ($published) {
            $region->publish();
        }

        return $region->refresh();
    }

    protected function tag(string $attributes = 'name="header" fallback="region-site::header"'): string
    {
        return Blade::render("<x-webx-blocks::region {$attributes} />");
    }

    private static function forgetDynamicComponents(): void
    {
        (new ReflectionProperty(DynamicComponent::class, 'compiler'))->setValue(null, null);
        (new ReflectionProperty(DynamicComponent::class, 'componentClasses'))->setValue(null, []);
    }
}
