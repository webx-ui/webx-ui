<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Contracts\Foundation\Application;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Help\HelpBuilder;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Testing\AssertsHelp;

/**
 * Every screen this package draws or patches is explained, field by field (WEBX_UI_PANEL_HELP.md §6),
 * with the fields a setting takes away as well: their texts stay, and only a site that has the field
 * shows them (§2.3).
 */
final class HelpCoverageTest extends TestCase
{
    use AssertsHelp;

    private const string CATEGORY = 'catalog.category-form';

    #[Test]
    public function every_screen_and_field_is_explained(): void
    {
        $this->assertScreensHaveHelp();
    }

    #[Test]
    #[DefineEnvironment('withCategoryFacets')]
    public function a_site_with_the_filters_of_a_category_has_them_explained(): void
    {
        $this->assertScreensHaveHelp();
        $this->assertContains('facets', $this->idsInTheAnswer());
        $this->assertContains('facets', $this->idsForAnAgent());
    }

    #[Test]
    #[DefineEnvironment('withoutOptionalFields')]
    public function a_site_with_the_optional_fields_off_is_as_healthy(): void
    {
        $ids = array_column($this->app->make(ScreenRegistry::class)->fields('catalog.product-form'), 'id');

        $this->assertNotContains('mpn', $ids);
        $this->assertScreensHaveHelp();
    }

    #[Test]
    public function a_field_the_site_has_switched_off_is_not_in_the_help(): void
    {
        $this->assertNotContains('facets', $this->idsInTheAnswer());
        $this->assertNotContains('facets', $this->idsForAnAgent());
    }

    /**
     * @return list<string>
     */
    private function idsInTheAnswer(): array
    {
        $groups = $this->actingAs($this->editor(['catalog.view']), 'cms')
            ->getJson('/api/cms/help/'.self::CATEGORY)
            ->assertOk()
            ->json('data.groups');

        return array_column(array_merge(...array_column($groups, 'fields')), 'id');
    }

    /**
     * @return list<string>
     */
    private function idsForAnAgent(): array
    {
        $help = $this->app->make(HelpBuilder::class)->build(
            self::CATEGORY,
            'en',
            static fn (string $permission): bool => true,
            static fn (string $words): string => $words,
            true,
        );

        return array_column(array_merge(...array_column($help['groups'] ?? [], 'fields')), 'id');
    }

    /**
     * @param  Application  $app
     */
    protected function withCategoryFacets($app): void
    {
        $app['config']->set('webx-catalog.fields.facets', true);
    }

    /**
     * @param  Application  $app
     */
    protected function withoutOptionalFields($app): void
    {
        $app['config']->set('webx-catalog.price.enabled', false);
        $app['config']->set('webx-catalog.fields.barcode', false);
        $app['config']->set('webx-catalog.fields.mpn', false);
    }
}
