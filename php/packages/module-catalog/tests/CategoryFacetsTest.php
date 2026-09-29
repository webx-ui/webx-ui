<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Catalog\Facets\CategoryFacets;
use WebxUi\Catalog\Facets\Facet;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Panel\FacetSettings;
use WebxUi\Catalog\Tests\Fixtures\ColourFacet;

/**
 * §6.2 and §16: which facets a category shows — its own settings, the nearest configured
 * ancestor's, or the whole registry — and a facet registered later stays hidden where somebody
 * arranged the filter by hand.
 */
final class CategoryFacetsTest extends TestCase
{
    #[Test]
    public function with_no_settings_anywhere_a_category_shows_every_facet_in_the_registrys_order(): void
    {
        $laptops = $this->category('laptops');

        $this->assertSame(['category', 'price'], $this->keys($laptops));
        $this->assertSame(['from' => null, 'facets' => null], $this->facets()->resolve($laptops));
    }

    #[Test]
    public function a_category_inherits_from_its_nearest_configured_ancestor(): void
    {
        $computers = $this->category('computers');
        $laptops = $this->category('laptops', parent: $computers);
        $gaming = $this->category('gaming-laptops', parent: $laptops);

        FacetSettings::write($computers, [['key' => 'category', 'visible' => true], ['key' => 'price', 'visible' => true]]);
        FacetSettings::write($laptops, [['key' => 'price', 'visible' => true], ['key' => 'category', 'visible' => false]]);

        $this->assertSame(['price'], $this->keys($gaming));
        $this->assertSame($laptops->id, $this->facets()->resolve($gaming)['from']);

        // Back to inheriting: the grandparent answers now, and the cache knows it.
        FacetSettings::write($laptops, null);

        $this->assertSame(['category', 'price'], $this->keys($gaming));
        $this->assertSame($computers->id, $this->facets()->resolve($gaming)['from']);
    }

    #[Test]
    public function a_facet_registered_after_the_settings_is_hidden_until_switched_on(): void
    {
        $laptops = $this->category('laptops');
        $phones = $this->category('phones');

        FacetSettings::write($laptops, [['key' => 'price', 'visible' => true], ['key' => 'category', 'visible' => true]]);

        ColourFacet::migrate();
        $this->app->make(Facets::class)->register(new ColourFacet);

        // Arranged by hand: the newcomer does not appear on its own.
        $this->assertSame(['price', 'category'], $this->keys($laptops));
        // Nobody arranged this one: it shows the registry, the newcomer included.
        $this->assertSame(['category', 'price', 'colour'], $this->keys($phones));
    }

    #[Test]
    public function a_key_whose_module_is_gone_is_skipped_and_kept(): void
    {
        $laptops = $this->category('laptops');

        FacetSettings::write($laptops, [['key' => 'brand', 'visible' => true], ['key' => 'price', 'visible' => true]]);

        $this->assertSame(['price'], $this->keys($laptops));
        $this->assertSame('brand', FacetSettings::of($laptops)[0]['key'] ?? null);
    }

    #[Test]
    public function moving_a_category_changes_whom_it_inherits_from(): void
    {
        $computers = $this->category('computers');
        $phones = $this->category('phones');
        $laptops = $this->category('laptops', parent: $computers);

        FacetSettings::write($computers, [['key' => 'price', 'visible' => true]]);
        FacetSettings::write($phones, [['key' => 'category', 'visible' => true]]);

        $this->assertSame(['price'], $this->keys($laptops));

        $laptops->refresh()->appendTo($phones->refresh());

        $this->assertSame(['category'], $this->keys($laptops->refresh()));
    }

    /** @return list<string> */
    private function keys(Category $category): array
    {
        return array_map(static fn (Facet $facet): string => $facet->key(), $this->facets()->visible($category));
    }

    private function facets(): CategoryFacets
    {
        return $this->app->make(CategoryFacets::class);
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        // On, as on a site with the properties: off, there is nothing here to arrange.
        $app['config']->set('webx-catalog.fields.facets', true);
    }
}
