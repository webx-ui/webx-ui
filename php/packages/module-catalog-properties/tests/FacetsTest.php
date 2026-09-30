<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Engine\CatalogQuery;
use WebxUi\Catalog\Engine\CatalogResult;
use WebxUi\Catalog\Facets\FacetKind;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Filter\FilterContext;
use WebxUi\Catalog\Models\Category;
use WebxUi\CatalogProperties\Catalog\ProductValues;
use WebxUi\CatalogProperties\Models\Property;

/**
 * §3.1, §4.4, §5 and §13 of the properties spec: each type is its own facet; flags a type has no
 * use for are put out; a text is never a facet; «no» is no row; a tree counts every ancestor and
 * refuses a middle node where only leaves go; intervals are `[min, max)` with open ends and each of
 * several overlapping ones counted; every facet not chosen counts in one statement; away from a
 * category the facets are picked by coverage; a code is checked against every facet.
 */
final class FacetsTest extends TestCase
{
    #[Test]
    public function the_flags_a_type_has_no_use_for_are_put_out_and_a_text_is_never_a_facet(): void
    {
        $weight = $this->property('Weight', Property::NUMBER, ['is_multiple' => true, 'has_color' => true, 'is_tree' => true, 'value_order' => 'manual']);
        $this->assertFalse($weight->is_multiple);
        $this->assertFalse($weight->has_color);
        $this->assertFalse($weight->is_tree);
        $this->assertSame(Property::SLIDER, $weight->filter_mode);
        $this->assertFalse($weight->is_indexable);

        $kit = $this->property('Kit', Property::TEXT, ['is_filterable' => true, 'is_indexable' => true]);
        $this->assertFalse($kit->is_filterable);
        $this->assertTrue($kit->is_searchable);
        $this->assertNull($this->app->make(Facets::class)->find($kit->facetKey()));

        $colour = $this->property('Colour');
        $this->assertTrue($colour->is_indexable);
        $this->assertTrue($colour->is_searchable);
        $this->assertSame('colour', $colour->codeIn('en'));
        $this->assertSame(FacetKind::Terms, $this->app->make(Facets::class)->find($colour->facetKey())?->kind());

        $this->expectException(ValidationException::class);
        $colour->update(['type' => Property::NUMBER]);
    }

    #[Test]
    public function a_code_is_checked_against_every_facet_in_its_language(): void
    {
        $this->property('Colour');

        try {
            $this->property('Price');
            $this->fail('The core holds `price`.');
        } catch (ValidationException $refused) {
            $this->assertSame(['This code is taken in this language by: Price.'], $refused->errors()['code.en']);
        }

        try {
            $this->property('Hue', attributes: ['code' => ['en' => 'colour']]);
            $this->fail('Colour holds `colour`.');
        } catch (ValidationException $refused) {
            $this->assertSame(['This code is taken in this language by: Colour.'], $refused->errors()['code.en']);
        }

        $this->expectException(ValidationException::class);
        $this->property('Size', attributes: ['code' => ['en' => 'size_eu']]);
    }

    #[Test]
    public function a_yes_no_keeps_only_yes_and_a_leaf_only_tree_refuses_a_middle_node(): void
    {
        $laptops = $this->category('laptops');
        $wifi = $this->property('Wi-Fi', Property::BOOL);
        $material = $this->property('Material', attributes: ['is_tree' => true, 'leaves_only' => true]);
        $metal = $this->value($material, 'Metal');
        $steel = $this->value($material, 'Steel', $metal);
        $this->set($laptops, [$wifi, $material]);
        $product = $this->product('Thing', $laptops);
        $values = $this->app->make(ProductValues::class);

        $values->put($product, $wifi, true);
        $this->assertSame(1, DB::table('catalog_product_property_values')->where('property_id', $wifi->id)->count());
        $values->put($product, $wifi, false);
        $this->assertSame(0, DB::table('catalog_product_property_values')->where('property_id', $wifi->id)->count());

        $values->put($product, $material, $steel->id);

        try {
            $values->put($product, $material, $metal->id);
            $this->fail('A middle node is refused where only leaves go.');
        } catch (ValidationException $refused) {
            $this->assertArrayHasKey("properties.values.{$material->id}", $refused->errors());
        }
    }

    #[Test]
    public function each_type_is_counted_as_its_facet_and_the_unchosen_ones_in_one_statement(): void
    {
        $laptops = $this->category('laptops');
        $colour = $this->property('Colour', attributes: ['is_multiple' => true]);
        $material = $this->property('Material', attributes: ['is_tree' => true]);
        $screen = $this->property('Screen', Property::NUMBER);
        $wifi = $this->property('Wi-Fi', Property::BOOL);
        $black = $this->value($colour, 'Black');
        $white = $this->value($colour, 'White');
        $metal = $this->value($material, 'Metal');
        $steel = $this->value($material, 'Steel', $metal);
        $aluminium = $this->value($material, 'Aluminium', $metal);
        $this->set($laptops, [$colour, $material, $screen, $wifi]);

        $one = $this->product('One', $laptops, [$colour->id => [$black, $white], $material->id => $steel, $screen->id => 13.3, $wifi->id => true]);
        $this->product('Two', $laptops, [$colour->id => [$black], $material->id => $aluminium, $screen->id => 15.6]);
        $this->product('Three', $laptops, [$screen->id => 17.0]);

        $keys = [$colour->facetKey(), $material->facetKey(), $screen->facetKey(), $wifi->facetKey()];

        DB::enableQueryLog();
        $result = $this->search($laptops, $keys);
        $queries = array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains($query['query'], 'catalog_product_property_values'));
        DB::disableQueryLog();

        $this->assertSame([(string) $black->id => 2, (string) $white->id => 1], $this->sorted($result->facet($colour->facetKey())?->counts));
        $this->assertSame([(string) $metal->id => 2, (string) $steel->id => 1, (string) $aluminium->id => 1], $this->sorted($result->facet($material->facetKey())?->counts));
        $range = $result->facet($screen->facetKey());
        $this->assertNotNull($range);
        $this->assertSame([13.3, 17.0], [$range->min, $range->max]);
        $this->assertSame(1, $result->facet($wifi->facetKey())?->count);
        // One per kind: terms, tree, range, toggle — not one per property.
        $this->assertLessThanOrEqual(4, count($queries));

        // A chosen ancestor finds what is under it; a chosen facet does not narrow its own counts.
        $chosen = $this->search($laptops, $keys, [$material->facetKey() => FacetValue::of([(string) $metal->id]), $colour->facetKey() => FacetValue::of([(string) $white->id])]);
        $this->assertSame([$one->id], array_map('intval', $chosen->ids));
        $this->assertSame([(string) $black->id => 2, (string) $white->id => 1], $this->sorted($chosen->facet($colour->facetKey())?->counts));
    }

    #[Test]
    public function intervals_are_half_open_with_open_ends_and_overlapping_ones_counted_each(): void
    {
        $laptops = $this->category('laptops');
        $weight = $this->property('Weight', Property::NUMBER, ['filter_mode' => Property::INTERVALS]);
        $this->set($laptops, [$weight]);
        $light = $this->product('Light', $laptops, [$weight->id => 1.0]);
        $two = $this->product('Two', $laptops, [$weight->id => 2.0]);
        $heavy = $this->product('Heavy', $laptops, [$weight->id => 3.5]);

        $editor = $this->editor();
        $this->clearQueue();
        $intervals = $this->actingAs($editor, 'cms')->putJson($this->api("properties/{$weight->id}/intervals"), ['intervals' => [
            ['title' => 'Up to 2 kg', 'slug' => 'up-to-2', 'min' => null, 'max' => 2],
            ['title' => '2–3 kg', 'slug' => '2-3', 'min' => 2, 'max' => 3],
            ['title' => 'Up to 5 kg', 'slug' => 'up-to-5', 'min' => null, 'max' => 5],
            ['title' => '3 kg and more', 'slug' => 'from-3', 'min' => 3, 'max' => null],
        ]])->assertOk()->json('data');

        // New intervals are new words in the documents of the property's products.
        $this->assertSame([$light->id, $two->id, $heavy->id], $this->queued());

        [$upTo2, $twoTo3, $upTo5, $from3] = array_map(static fn (array $row): string => (string) $row['id'], $intervals);
        $result = $this->search($laptops, [$weight->facetKey()]);

        $this->assertSame([$upTo2 => 1, $twoTo3 => 1, $upTo5 => 3, $from3 => 1], $this->sorted($result->facet($weight->facetKey())?->counts));

        $chosen = $this->search($laptops, [$weight->facetKey()], [$weight->facetKey() => FacetValue::of([$twoTo3])]);
        $this->assertSame([$two->id], array_map('intval', $chosen->ids));

        $this->get('/laptops/weight_2-3')->assertOk()->assertSee('Two')->assertDontSee('Heavy');

        $this->actingAs($editor, 'cms')->putJson($this->api("properties/{$weight->id}/intervals"), ['intervals' => [
            ['title' => 'Bad', 'min' => 3, 'max' => 2],
        ]])->assertStatus(422);
    }

    #[Test]
    public function away_from_a_category_the_facets_are_picked_by_coverage_and_a_chosen_one_stays_open(): void
    {
        $this->app['config']->set('webx-catalog-properties.dynamic_facets', ['min_share' => 0.3, 'limit' => 1]);
        $laptops = $this->category('laptops');
        $common = $this->property('Colour');
        $half = $this->property('Size');
        $rare = $this->property('Rare');
        $none = $this->property('None');
        $this->set($laptops, [$common, $half, $rare, $none]);
        $black = $this->value($common, 'Black');
        $big = $this->value($half, 'Big');
        $odd = $this->value($rare, 'Odd');

        for ($i = 0; $i < 10; $i++) {
            $values = [$common->id => $black];

            if ($i < 5) {
                $values[$half->id] = $big;
            }

            if ($i === 0) {
                $values[$rare->id] = $odd;
            }

            $this->product('Product '.$i, $laptops, $values);
        }

        $keys = [$common->facetKey(), $half->facetKey(), $rare->facetKey(), $none->facetKey()];
        $result = $this->app->make(Catalog::class)->engine()->search(new CatalogQuery(locale: 'en', context: FilterContext::SEARCH, count: $keys));

        $this->assertTrue($result->facet($common->facetKey())?->expanded);
        $this->assertFalse($result->facet($half->facetKey())?->expanded);
        $this->assertFalse($result->facet($rare->facetKey())?->expanded);
        $this->assertNull($result->facet($none->facetKey()));

        $chosen = $this->app->make(Catalog::class)->engine()->search(new CatalogQuery(
            locale: 'en',
            context: FilterContext::SEARCH,
            facets: [$rare->facetKey() => FacetValue::of([(string) $odd->id])],
            count: $keys,
        ));
        $this->assertTrue($chosen->facet($rare->facetKey())?->expanded);

        // On the category's page every property of the set in the filter is open.
        $page = $this->search($laptops, $keys);
        $this->assertTrue($page->facet($rare->facetKey())?->expanded);
        $this->assertTrue($page->facet($half->facetKey())?->expanded);
    }

    #[Test]
    public function a_renamed_code_and_slug_lead_to_the_new_address(): void
    {
        $laptops = $this->category('laptops');
        $colour = $this->property('Colour');
        $black = $this->value($colour, 'Black');
        $this->set($laptops, [$colour]);
        $this->product('Thing', $laptops, [$colour->id => $black]);

        $this->get('/laptops/colour_black')->assertOk()->assertSee('Thing');

        $colour->update(['code' => ['en' => 'color']]);
        $black->update(['slug' => ['en' => 'jet-black']]);

        $this->get('/laptops/colour_black')->assertStatus(301)->assertRedirect('/laptops/color_jet-black');
        $this->get('/laptops/color_jet-black')->assertOk()->assertSee('Thing');
    }

    /**
     * @param  list<string>  $keys
     * @param  array<string, FacetValue>  $facets
     */
    private function search(Category $category, array $keys, array $facets = []): CatalogResult
    {
        return $this->app->make(Catalog::class)->engine()->search(new CatalogQuery(
            locale: 'en',
            context: FilterContext::CATEGORY,
            contextId: $category->id,
            scope: ['category' => FacetValue::of([(string) $category->id])],
            facets: $facets,
            count: $keys,
        ));
    }

    /**
     * @param  array<string, int>|null  $counts
     * @return array<string, int>
     */
    private function sorted(?array $counts): array
    {
        $counts ??= [];
        ksort($counts);

        return $counts;
    }
}
