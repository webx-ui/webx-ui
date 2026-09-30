<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogProperties\Models\Property;
use WebxUi\CatalogProperties\Models\PropertyGroup;
use WebxUi\CatalogProperties\Models\PropertyValue;
use WebxUi\CatalogProperties\Storefront\ProductProperties;
use WebxUi\CatalogProperties\Storefront\ShownProperty;

/**
 * §8 and §13 of the properties spec, the storefront: the card and the table by group in the order
 * of the set; a reference book's value links to its first level where that page is open; the
 * first level is open only on a category's page and takes the property's `seo_pattern`; a value
 * outside the set is shown nowhere; swatches in the filter; the map of the site; the template's
 * query.
 */
final class StorefrontTest extends TestCase
{
    private Category $laptops;

    private Property $wifi;

    private Property $diagonal;

    private Property $colour;

    private Property $material;

    private PropertyValue $black;

    private PropertyValue $grey;

    private Product $thinkpad;

    protected function setUp(): void
    {
        parent::setUp();

        $screen = PropertyGroup::query()->create(['title' => 'Screen', 'is_visible' => true]);
        $general = PropertyGroup::query()->create(['title' => 'General', 'is_visible' => true]);

        $this->laptops = $this->category('laptops');
        $this->wifi = $this->property('Wi-Fi', Property::BOOL, ['on_page' => true]);
        $this->diagonal = $this->property('Diagonal', Property::NUMBER, ['group_id' => $screen->id, 'in_card' => true, 'precision' => 1, 'unit_suffix' => '″']);
        $this->colour = $this->property('Colour', Property::SELECT, ['group_id' => $general->id, 'in_card' => true, 'is_multiple' => true, 'has_color' => true]);
        $this->material = $this->property('Material', Property::SELECT, ['group_id' => $general->id, 'in_card' => true, 'is_tree' => true]);

        $this->black = $this->value($this->colour, 'Black');
        $this->black->update(['color' => '#000000']);
        $this->grey = $this->value($this->colour, 'Grey');
        $steel = $this->value($this->material, 'Steel');

        // The set's order is not the properties' own: Wi-Fi, then Diagonal, then Colour. Material
        // is not in it, and a product's value of it is kept and shown nowhere.
        $this->set($this->laptops, [$this->wifi, $this->diagonal, $this->colour]);

        $this->thinkpad = $this->product('ThinkPad', $this->laptops, [
            $this->wifi->id => true,
            $this->diagonal->id => 15.6,
            $this->colour->id => [$this->black, $this->grey],
            $this->material->id => $steel,
        ]);
    }

    #[Test]
    public function the_card_lists_the_properties_marked_for_it_in_the_order_of_the_set(): void
    {
        $card = $this->app->make(ProductProperties::class)->card($this->thinkpad);

        $this->assertSame(['Diagonal', 'Colour'], array_map(static fn (ShownProperty $one): string => $one->label, $card));
        $this->assertSame(['15.6″', 'Black, Grey'], array_map(static fn (ShownProperty $one): string => $one->formatted, $card));

        $this->product('Old', $this->laptops, [$this->diagonal->id => 13.3]);
        $this->product('Big', $this->laptops, [$this->diagonal->id => 17.0]);

        DB::enableQueryLog();

        $this->get('/laptops')->assertOk()
            ->assertSeeInOrder(['ThinkPad', 'Diagonal:', '15.6″', 'Colour:', 'Black, Grey'], false)
            ->assertSee('17.0″')
            ->assertDontSee('Steel');

        // A page of cards is one read of the values, the words of the books joined in.
        $reads = array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains($query['query'], 'catalog_product_property_values" as "stored" left join'));
        $this->assertCount(1, $reads);
    }

    #[Test]
    public function the_table_goes_by_group_in_the_order_of_the_set_and_links_an_open_first_level(): void
    {
        $page = $this->get((string) $this->thinkpad->url())->assertOk();

        // Groups in the order their first property stands in the set; without a group — last.
        $page->assertSeeInOrder(['Specifications', 'Screen', 'Diagonal', '15.6″', 'General', 'Colour', 'Black', 'Grey', 'Wi-Fi', 'Yes'], false)
            ->assertSee('href="http://localhost/laptops/colour_black"', false)
            ->assertSee('href="http://localhost/laptops/colour_grey"', false)
            ->assertDontSee('Material')
            ->assertDontSee('Steel');

        // A toggle and a number have no page of their own; a book closed to the index is text.
        $page->assertDontSee('wi-fi_yes', false)->assertDontSee('diagonal_', false);

        $this->colour->update(['is_indexable' => false]);
        $this->get((string) $this->thinkpad->url())->assertOk()->assertSee('Black')->assertDontSee('colour_black', false);
    }

    #[Test]
    public function a_template_reads_the_properties_of_a_product(): void
    {
        /** @var list<ShownProperty> $shown */
        $shown = $this->thinkpad->properties(); // @phpstan-ignore method.notFound

        $this->assertSame(['wi-fi', 'diagonal', 'colour'], array_map(static fn (ShownProperty $one): string => $one->code, $shown));
        $this->assertTrue($shown[0]->value);
        $this->assertSame(15.6, $shown[1]->value);
        $this->assertSame(['id' => (int) $shown[1]->group['id'], 'title' => 'Screen'], $shown[1]->group);
        $this->assertSame([(int) $this->black->id, (int) $this->grey->id], $shown[2]->value);
        $this->assertNull($shown[2]->url, 'two values, two addresses — in `values`');
        $this->assertSame('http://localhost/laptops/colour_black', $shown[2]->values[0]['url']);
        $this->assertSame('#000000', $shown[2]->values[0]['color']);
        $this->assertNull($shown[0]->group);
    }

    #[Test]
    public function a_value_outside_the_set_comes_back_with_the_category(): void
    {
        $this->set($this->laptops, [$this->wifi, $this->diagonal, $this->colour, $this->material]);

        $card = $this->app->make(ProductProperties::class)->card($this->thinkpad->refresh());

        $this->assertSame(['Diagonal', 'Colour', 'Material'], array_map(static fn (ShownProperty $one): string => $one->label, $card));
    }

    #[Test]
    public function the_first_level_takes_the_seo_pattern_of_the_property(): void
    {
        $this->get('/laptops/colour_black')->assertOk()
            ->assertSee('<h1>Laptops Black</h1>', false)
            ->assertDontSee('noindex', false);

        $this->colour->update(['seo_pattern' => ['en' => '{category} in {value}, by {property}']]);

        $this->get('/laptops/colour_black')->assertOk()
            ->assertSee('<title>Laptops in Black, by Colour', false)
            ->assertSee('<h1>Laptops in Black, by Colour</h1>', false)
            ->assertDontSee('noindex', false);
    }

    #[Test]
    #[DefineEnvironment('withRoot')]
    public function the_first_level_of_a_property_is_closed_away_from_a_category(): void
    {
        $this->get('/catalog')->assertOk()->assertSee('/catalog/colour_black" rel="nofollow"', false);
        $this->get('/catalog/colour_black')->assertOk()->assertSee('ThinkPad')->assertSee('noindex, follow', false);
    }

    #[Test]
    public function the_filter_draws_a_swatch_before_a_value_with_a_colour(): void
    {
        $this->get('/laptops')->assertOk()
            ->assertSeeInOrder(['colour_black', '<span class="webx-catalog-filter__swatch" style="background-color: #000000" aria-hidden="true"></span>', 'Black', 'colour_grey" >Grey'], false)
            ->assertSee('background-color: #000000', false)
            ->assertDontSee('webx-catalog-filter__swatch" style="background-color: ;', false);
    }

    #[Test]
    public function the_map_of_the_site_lists_the_open_first_levels_that_are_not_empty(): void
    {
        $this->value($this->colour, 'White');
        $desks = $this->category('desks');
        $this->set($desks, [$this->material]);

        $map = (string) $this->get('/sitemap-catalog-filters.xml')->assertOk()->getContent();

        $this->assertStringContainsString('/laptops/colour_black<', $map);
        $this->assertStringContainsString('/laptops/colour_grey<', $map);
        // White has no products; a toggle and a slider are never pages; Material is in no set here
        // but the desks', and no desk has it.
        $this->assertStringNotContainsString('colour_white', $map);
        $this->assertStringNotContainsString('wi-fi_', $map);
        $this->assertStringNotContainsString('diagonal_', $map);
        $this->assertStringNotContainsString('material_', $map);
    }

    #[Test]
    public function a_template_narrows_its_products_by_a_property(): void
    {
        $metal = $this->value($this->material, 'Metal');
        $steel = PropertyValue::query()->where('property_id', $this->material->id)->where('title->en', 'Steel')->firstOrFail();
        $steel->appendTo($metal->refresh());
        $this->set($this->laptops, [$this->wifi, $this->diagonal, $this->colour, $this->material]);

        $this->product('Old', $this->laptops, [$this->diagonal->id => 13.3, $this->colour->id => [$this->grey]]);
        $this->product('Big', $this->laptops, [$this->diagonal->id => 17.0]);

        $names = static fn (iterable $cards): array => array_values(array_map(static fn (array $card): string => $card['name'], [...$cards]));

        $this->assertSame(['ThinkPad'], $names(products()->property('colour', 'black')));
        $either = $names(products()->property('colour', ['black', (string) $this->grey->id]));
        sort($either);
        $this->assertSame(['Old', 'ThinkPad'], $either);
        $this->assertSame(['ThinkPad'], $names(products()->property('material', 'metal')));
        $this->assertSame(['ThinkPad'], $names(products()->property('wi-fi')));
        $this->assertSame(['Big'], $names(products()->property('diagonal', min: 16)));
        $this->assertCount(2, products()->property('diagonal', min: 13.3, max: 15.6)->get());
        $this->assertSame(['ThinkPad'], $names(products()->property('colour', 'black')->property('diagonal', max: 16)));
        $this->assertSame([], products()->property('colour', 'purple')->get());
        $this->assertSame([], products()->property('nobody', 'black')->get());

        // Out of the set, a value matches no choice.
        $this->set($this->laptops, [$this->diagonal]);
        $this->assertSame([], products()->property('colour', 'black')->get());
    }

    /**
     * @param  Application  $app
     */
    protected function withRoot($app): void
    {
        $app['config']->set('webx-catalog.root.enabled', true);
    }
}
