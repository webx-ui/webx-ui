<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Catalog\Facets\CategoryFacets;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Panel\FacetSettings;

/**
 * The «Filters» tab of a category is off by default (`webx-catalog.fields.facets`): with the
 * category and the price alone there is nothing to arrange. Off, nobody can write the setting,
 * and one written while it was on is kept but no longer decides what a visitor is offered.
 */
final class CategoryFacetsOffTest extends TestCase
{
    #[Test]
    public function by_default_the_tab_is_gone_and_what_was_chosen_is_not_applied(): void
    {
        $names = array_column($this->app->make(ScreenRegistry::class)->fields(Category::SCREEN), 'name');
        $this->assertNotContains('facets', $names);

        $laptops = $this->category('laptops');
        FacetSettings::write($laptops, [['key' => 'price', 'visible' => true], ['key' => 'category', 'visible' => false]]);

        $facets = $this->app->make(CategoryFacets::class);
        $this->assertSame(['from' => null, 'facets' => null], $facets->resolve($laptops));
        $this->assertSame(['category', 'price'], array_map(static fn ($facet): string => $facet->key(), $facets->visible($laptops)));
    }
}
