<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Catalog\Filter\FilterAliases;
use WebxUi\Catalog\Models\Category;
use WebxUi\CatalogBrands\Models\Brand;
use WebxUi\CatalogLandings\Models\Landing;
use WebxUi\CatalogProperties\Models\Property;
use WebxUi\CatalogProperties\Models\PropertyValue;

/**
 * §9 and §6.5 of the landings spec: the values a set holds by id, when they stop being
 * themselves, and the count that follows the list.
 */
final class ValuesTest extends TestCase
{
    private Category $laptops;

    private Brand $apple;

    private Brand $dell;

    protected function setUp(): void
    {
        parent::setUp();

        $this->laptops = $this->category('laptops');
        $this->apple = $this->brand('Apple');
        $this->dell = $this->brand('Dell');
        $this->product('MacBook Air', $this->laptops, $this->apple, ['price' => 900]);
        $this->product('XPS', $this->laptops, $this->dell, ['price' => 1200]);
    }

    #[Test]
    public function a_merged_value_is_followed_quietly(): void
    {
        $landing = $this->landing('laptops-apple', $this->laptops, ['brand' => $this->brands([$this->apple])]);

        $this->app->make(FilterAliases::class)->retarget('brand', FilterAliases::VALUE, (string) $this->apple->id, (string) $this->dell->id);

        $landing->refresh();
        $this->assertSame(['brand' => ['values' => [(string) $this->dell->id]]], $landing->filters);
        $this->assertNull($landing->attention);
        $this->assertTrue($landing->is_published);
        $this->get('/laptops-apple')->assertOk()->assertSee('XPS')->assertDontSee('MacBook Air');
        $this->get('/laptops/brand_dell')->assertStatus(301)->assertRedirect('/laptops-apple');
    }

    #[Test]
    public function a_merge_that_makes_the_set_another_landings_unpublishes_it_marked(): void
    {
        $this->landing('laptops-dell', $this->laptops, ['brand' => $this->brands([$this->dell])]);
        $landing = $this->landing('laptops-apple', $this->laptops, ['brand' => $this->brands([$this->apple])]);

        $this->app->make(FilterAliases::class)->retarget('brand', FilterAliases::VALUE, (string) $this->apple->id, (string) $this->dell->id);

        $landing->refresh();
        $this->assertSame(Landing::DUPLICATE, $landing->attention);
        $this->assertFalse($landing->is_published);
        $this->assertNull($landing->filters_hash);
        $this->get('/laptops/brand_dell')->assertStatus(301)->assertRedirect('/laptops-dell');
    }

    #[Test]
    public function a_deleted_value_drops_out_marked_and_an_emptied_set_is_unpublished(): void
    {
        $two = $this->landing('cheap-apple', $this->laptops, ['brand' => $this->brands([$this->apple]), 'price' => ['max' => 1000]]);
        $one = $this->landing('laptops-apple', $this->laptops, ['brand' => $this->brands([$this->apple])]);
        $both = $this->landing('apple-dell', $this->laptops, ['brand' => $this->brands([$this->apple, $this->dell])]);

        // A brand products are of cannot go; one nobody is of can.
        DB::table(Brand::LINKS)->where('brand_id', $this->apple->id)->delete();
        $this->apple->delete();

        $two->refresh();
        $this->assertSame(['price' => ['min' => null, 'max' => 1000]], $two->filters);
        $this->assertSame(Landing::VALUE_REMOVED, $two->attention);
        $this->assertTrue($two->is_published);

        $one->refresh();
        $this->assertSame([], $one->filters);
        $this->assertSame(Landing::EMPTY_SET, $one->attention);
        $this->assertFalse($one->is_published);
        $this->get('/laptops-apple')->assertNotFound();

        $both->refresh();
        $this->assertSame(['brand' => ['values' => [(string) $this->dell->id]]], $both->filters);
        $this->assertSame(Landing::VALUE_REMOVED, $both->attention);
    }

    #[Test]
    public function a_property_in_the_bin_drops_its_facet_at_once_on_the_page_and_in_the_set_at_the_count(): void
    {
        $colour = Property::query()->create(['title' => 'Colour', 'type' => Property::SELECT, 'is_filterable' => true])->refresh();
        $black = new PropertyValue(['property_id' => $colour->id, 'title' => 'Black']);
        $black->saveAsRoot();

        $landing = $this->landing('black-apple', $this->laptops, [
            'brand' => $this->brands([$this->apple]),
            $colour->facetKey() => ['values' => [(string) $black->id]],
        ]);

        $colour->delete();

        // The page reads the set without the facet at once.
        $this->get('/black-apple')->assertOk()->assertSee('MacBook Air');

        $this->artisan('webx:catalog-landings:count', ['--all' => true])->assertSuccessful();

        $landing->refresh();
        $this->assertSame(['brand' => ['values' => [(string) $this->apple->id]]], $landing->filters);
        $this->assertSame(Landing::VALUE_REMOVED, $landing->attention);
        $this->assertSame(1, $landing->products_count);
    }

    #[Test]
    public function a_base_in_the_bin_hides_the_landing_and_brings_it_back_unmarked(): void
    {
        // A category with products cannot go to the bin; an empty one can.
        $tablets = $this->category('tablets');
        $landing = $this->landing('tablets-apple', $tablets, ['brand' => $this->brands([$this->apple])]);

        $tablets->delete();
        $this->get('/tablets-apple')->assertNotFound();

        $tablets->restore();
        $this->get('/tablets-apple')->assertOk();
        $this->assertNull($landing->refresh()->attention);
    }

    #[Test]
    public function a_batch_of_the_index_on_the_base_recounts_the_landing(): void
    {
        $landing = $this->landing('laptops-apple', $this->laptops, ['brand' => $this->brands([$this->apple])]);
        $other = $this->landing('tablets-apple', $this->category('tablets'), ['brand' => $this->brands([$this->apple])]);
        $this->artisan('webx:catalog-landings:count', ['--all' => true]);
        $this->assertSame(1, $landing->refresh()->products_count);
        $counted = $other->refresh()->counted_at;

        $this->product('MacBook Pro', $this->laptops, $this->apple);
        $this->artisan('webx:catalog:index')->assertSuccessful();

        $this->assertSame(2, $landing->refresh()->products_count);
        $this->assertEquals($counted, $other->refresh()->counted_at);
    }
}
