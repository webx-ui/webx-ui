<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Tests;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Documents\Documents;
use WebxUi\Catalog\Engine\CatalogQuery;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogProperties\Catalog\PropertiesDocument;
use WebxUi\CatalogProperties\Catalog\PropertySets;
use WebxUi\CatalogProperties\Models\Property;

/**
 * §3.2, §7.3 and §13 of the properties spec: a set is the ancestors' properties then the
 * category's own; an ancestor's property cannot be added below it; a move marks the subtree in one
 * statement; the product form writes the values of the set in force — the one the save gives the
 * product — and a refusal takes the whole save back; a value outside the set is kept, shown and
 * indexed nowhere, and back when the category is.
 */
final class SetsTest extends TestCase
{
    #[Test]
    public function a_set_inherits_every_ancestor_first_and_refuses_an_ancestor_property(): void
    {
        $electronics = $this->category('electronics');
        $laptops = $this->category('laptops', $electronics);
        $gaming = $this->category('gaming', $laptops);
        $colour = $this->property('Colour');
        $weight = $this->property('Weight', Property::NUMBER);
        $gpu = $this->property('GPU');
        $screen = $this->property('Screen', Property::NUMBER);

        $this->set($electronics, [$weight, $colour]);
        $this->set($laptops, [$screen]);
        $this->set($gaming, [$gpu]);

        $sets = $this->app->make(PropertySets::class);
        $this->assertSame([$weight->id, $colour->id, $screen->id, $gpu->id], $sets->effective($gaming));
        $this->assertSame([$weight->id, $colour->id], $sets->effective($electronics));

        $editor = $this->editor();
        $this->actingAs($editor, 'cms')->putJson($this->api("categories/{$gaming->id}/properties"), ['ids' => [$gpu->id, $colour->id]])
            ->assertStatus(422)
            ->assertJsonPath('errors.ids.0', '«Colour» is already in the set of «Electronics» above; a category inherits it.');

        $this->actingAs($editor, 'cms')->getJson($this->api("categories/{$gaming->id}/properties"))->assertOk()
            ->assertJsonPath('data.inherited.0.property.id', $weight->id)
            ->assertJsonPath('data.inherited.0.from.name', 'Electronics')
            ->assertJsonPath('data.inherited.2.from.name', 'Laptops')
            ->assertJsonPath('data.own.0.id', $gpu->id);
    }

    #[Test]
    public function a_saved_set_and_a_moved_branch_mark_the_products_of_the_subtree_in_one_statement(): void
    {
        $electronics = $this->category('electronics');
        $laptops = $this->category('laptops', $electronics);
        $phones = $this->category('phones');
        $colour = $this->property('Colour');
        $macbook = $this->product('MacBook', $laptops);
        $iphone = $this->product('iPhone', $phones);
        $this->clearQueue();

        DB::enableQueryLog();
        $this->set($electronics, [$colour]);
        $inserts = array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains($query['query'], 'catalog_index_queue'));
        DB::disableQueryLog();

        $this->assertCount(1, $inserts);
        $this->assertSame([$macbook->id], $this->queued());

        $this->clearQueue();
        $laptops->refresh()->appendTo($phones->refresh());

        $this->assertSame([$colour->id], $this->app->make(PropertySets::class)->effective($electronics));
        $this->assertSame([], $this->app->make(PropertySets::class)->effective($laptops->refresh()));
        $this->assertSame([$macbook->id], $this->queued());
        $this->assertNotContains($iphone->id, $this->queued());
    }

    #[Test]
    public function the_product_form_writes_the_set_of_the_main_category_it_is_saved_with(): void
    {
        $laptops = $this->category('laptops');
        $phones = $this->category('phones');
        $colour = $this->property('Colour');
        $weight = $this->property('Weight', Property::NUMBER, ['unit_suffix' => ' kg', 'precision' => 2]);
        $black = $this->value($colour, 'Black');
        $grey = $this->value($colour, 'Grey');
        $this->set($laptops, [$colour, $weight]);
        $this->set($phones, [$colour]);
        $product = $this->product('Phone', $phones, [$colour->id => $black]);
        $editor = $this->editor();

        // Weight is not in the phones' set — until the same save moves the product to the laptops.
        $this->actingAs($editor, 'cms')->putJson($this->api("products/{$product->id}"), [
            'values' => ['properties.values' => [$weight->id => 1.35]],
        ])->assertStatus(422)->assertJsonValidationErrors(["properties.values.{$weight->id}"]);

        $values = $this->actingAs($editor, 'cms')->putJson($this->api("products/{$product->id}"), [
            'values' => ['category_id' => $laptops->id, 'properties.values' => [$weight->id => 1.35, $colour->id => $grey->id]],
        ])->assertOk()->json('data.values');

        $this->assertSame([$colour->id => $grey->id, $weight->id => 1.35], $values['properties.values']);

        $entry = HistoryEntry::query()->where('subject_type', Product::TYPE)->where('subject_id', $product->id)->latest('id')->firstOrFail();
        $changes = collect($entry->changes)->keyBy('field');
        $this->assertSame('Black', $changes["properties.values.{$colour->id}"]['from']);
        $this->assertSame('Grey', $changes["properties.values.{$colour->id}"]['to']);
        $this->assertSame('Colour', $changes["properties.values.{$colour->id}"]['label']);
        $this->assertNull($changes["properties.values.{$weight->id}"]['from']);
        $this->assertSame('1.35 kg', $changes["properties.values.{$weight->id}"]['to']);

        // A refusal of the part takes the product's own fields back with it.
        $this->actingAs($editor, 'cms')->putJson($this->api("products/{$product->id}"), [
            'values' => ['price' => 99, 'properties.values' => [$colour->id => 404]],
        ])->assertStatus(422)->assertJsonValidationErrors(["properties.values.{$colour->id}"]);
        $this->assertSame('10.00', number_format((float) $product->refresh()->price, 2, '.', ''));

        // Null takes a value away; a key left out is not touched.
        $values = $this->actingAs($editor, 'cms')->putJson($this->api("products/{$product->id}"), [
            'values' => ['properties.values' => [$weight->id => null]],
        ])->assertOk()->json('data.values');
        $this->assertSame([$colour->id => $grey->id], $values['properties.values']);
    }

    #[Test]
    public function a_value_outside_the_set_is_kept_and_shown_nowhere_until_the_category_has_it_again(): void
    {
        $laptops = $this->category('laptops');
        $phones = $this->category('phones');
        $colour = $this->property('Colour', attributes: ['is_searchable' => true]);
        $black = $this->value($colour, 'Black');
        $this->set($laptops, [$colour]);
        $product = $this->product('Thing', $laptops, [$colour->id => $black]);

        $product->update(['category_id' => $phones->id]);
        $editor = $this->editor();

        // Not in the form: apart, read only.
        $values = $this->actingAs($editor, 'cms')->getJson($this->api("products/{$product->id}"))->assertOk()->json('data.values');
        $this->assertSame([], $values['properties.values']);
        $this->assertSame([$colour->id => $black->id], $values['properties.outside']);

        // Not in the facet, not in the search, not in the document.
        $this->get('/phones')->assertOk()->assertDontSee('colour_black');
        $this->assertSame([], $this->searchIds('Black'));
        $document = $this->app->make(PropertiesDocument::class)->contribute(new Collection([$product->refresh()]), ['en']);
        $this->assertSame([], $document[$product->id]['pv']);

        // Back where it was, it is all there.
        $product->update(['category_id' => $laptops->id]);
        $this->assertSame([$product->id], $this->searchIds('Black'));
        $document = $this->app->make(PropertiesDocument::class)->contribute(new Collection([$product->refresh()]), ['en']);
        $this->assertSame([$black->id], $document[$product->id]['pv']);
        $this->assertSame('Black', $document[$product->id]['pt_en']);

        // «Delete values outside the set» is a bulk action, and it journals what it deleted.
        $product->update(['category_id' => $phones->id]);
        $this->actingAs($editor, 'cms')->postJson($this->api('bulk'), [
            'action' => 'clear-outside-set',
            'selection' => ['ids' => [$product->id]],
        ])->assertOk()->assertJsonPath('data.done', 1);
        $this->assertSame(0, DB::table('catalog_product_property_values')->where('product_id', $product->id)->count());

        $row = HistoryEntry::query()->where('subject_type', Product::TYPE)->where('subject_id', $product->id)->latest('id')->firstOrFail();
        $this->assertSame('Black', collect((array) $row->changes)->keyBy('field')["properties.values.{$colour->id}"]['from']);
    }

    #[Test]
    public function the_document_of_a_product_carries_its_properties_in_the_registry(): void
    {
        $laptops = $this->category('laptops');
        $wifi = $this->property('Wi-Fi', Property::BOOL);
        $this->set($laptops, [$wifi]);
        $product = $this->product('Thing', $laptops, [$wifi->id => true]);

        $document = $this->app->make(Documents::class)->build(new Collection([$product]), ['en'])[$product->id];

        $this->assertSame([$wifi->id], $document['pb']);
        $this->assertArrayHasKey('pt_en', $document);
    }

    /**
     * @return list<int>
     */
    private function searchIds(string $text): array
    {
        return array_map('intval', $this->app->make(Catalog::class)->engine()
            ->search(new CatalogQuery(locale: 'en', search: $text))->ids);
    }
}
