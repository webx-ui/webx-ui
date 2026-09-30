<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\CatalogProperties\Models\Property;
use WebxUi\CatalogProperties\Models\PropertyValue;

/**
 * §3.3, §7.3, §7.4 and §13 of the properties spec: the API of a property and its reference book; a
 * merge collapses a duplicate, leaves the old slugs leading to the target, moves the children and
 * writes one row; a value with products is not deleted (a 422 with the count); a property in the
 * bin takes its facet and its segment of every address away and keeps the values of the products
 * for a restore.
 */
final class ValuesTest extends TestCase
{
    #[Test]
    public function a_property_is_created_read_edited_ordered_and_listed_through_the_api(): void
    {
        $editor = $this->editor();

        $created = $this->actingAs($editor, 'cms')->postJson($this->api('properties'), ['values' => [
            'title' => ['en' => 'Screen size'],
            'type' => 'number',
            'unit_suffix' => ['en' => '″'],
            'precision' => 1,
            'is_filterable' => true,
            'is_multiple' => true,
        ]])->assertCreated()
            ->assertJsonPath('data.property.code.en', 'screen-size')
            ->assertJsonPath('data.property.is_multiple', false)
            ->assertJsonPath('data.property.filter_mode', 'slider')
            ->json('data.property');

        $id = (int) $created['id'];

        // Part of the fields: the rest stay.
        $this->actingAs($editor, 'cms')->putJson($this->api("properties/{$id}"), ['values' => ['title' => ['en' => 'Diagonal']]])
            ->assertOk()
            ->assertJsonPath('data.property.title.en', 'Diagonal')
            ->assertJsonPath('data.property.precision', 1)
            ->assertJsonPath('data.property.code.en', 'screen-size');

        $this->actingAs($editor, 'cms')->putJson($this->api("properties/{$id}"), ['values' => ['type' => 'select']])
            ->assertStatus(422)->assertJsonValidationErrors(['type']);

        $colour = $this->property('Colour');
        $this->actingAs($editor, 'cms')->postJson($this->api('properties/reorder'), ['ids' => [$colour->id, $id]])->assertOk();

        $this->actingAs($editor, 'cms')->getJson($this->api('properties'))->assertOk()
            ->assertJsonPath('data.0.id', $colour->id)
            ->assertJsonPath('data.1.id', $id);

        $this->actingAs($editor, 'cms')->getJson($this->api('properties?type=number'))->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title.en', 'Diagonal');

        $row = HistoryEntry::query()->where('subject_type', Property::TYPE)->where('subject_id', $id)->where('event', 'updated')->firstOrFail();
        $this->assertSame(['title.en'], collect((array) $row->changes)->pluck('field')->all());

        // Reading only, writing takes `catalog.manage`.
        $reader = $this->editor(['catalog.view']);
        $this->actingAs($reader, 'cms')->getJson($this->api("properties/{$id}"))->assertOk();
        $this->actingAs($reader, 'cms')->putJson($this->api("properties/{$id}"), ['values' => ['title' => ['en' => 'X']]])->assertForbidden();
    }

    #[Test]
    public function the_reference_book_is_a_lazy_tree_with_a_search_and_moves(): void
    {
        $material = $this->property('Material', attributes: ['is_tree' => true, 'value_order' => Property::MANUAL]);
        $editor = $this->editor();

        $metal = $this->actingAs($editor, 'cms')->postJson($this->api("properties/{$material->id}/values"), ['title' => ['en' => 'Metal']])
            ->assertCreated()->assertJsonPath('data.slug.en', 'metal')->json('data.id');
        $steel = $this->actingAs($editor, 'cms')->postJson($this->api("properties/{$material->id}/values"), ['title' => ['en' => 'Steel'], 'parent_id' => $metal, 'color' => '#AABBCC'])
            ->assertCreated()->assertJsonPath('data.color', '#aabbcc')->json('data.id');
        $wood = $this->actingAs($editor, 'cms')->postJson($this->api("properties/{$material->id}/values"), ['title' => ['en' => 'Wood']])->json('data.id');

        $this->actingAs($editor, 'cms')->getJson($this->api("properties/{$material->id}/values"))->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $metal)
            ->assertJsonPath('data.0.has_children', true);

        $this->actingAs($editor, 'cms')->getJson($this->api("properties/{$material->id}/values?parent_id={$metal}"))->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $steel);

        $this->actingAs($editor, 'cms')->getJson($this->api("properties/{$material->id}/values?q=ste"))->assertOk()
            ->assertJsonPath('data.0.id', $steel)->assertJsonPath('total', 1);

        $this->actingAs($editor, 'cms')->postJson($this->api("properties/{$material->id}/values/{$wood}/move"), ['parent_id' => null, 'before_id' => $metal])->assertOk();
        $this->actingAs($editor, 'cms')->getJson($this->api("properties/{$material->id}/values"))->assertJsonPath('data.0.id', $wood);

        $this->actingAs($editor, 'cms')->postJson($this->api("properties/{$material->id}/values/{$metal}/move"), ['parent_id' => $steel])
            ->assertStatus(422);
    }

    #[Test]
    public function a_merge_collapses_duplicates_moves_children_and_leaves_the_old_slug_leading_to_the_target(): void
    {
        $laptops = $this->category('laptops');
        $colour = $this->property('Colour', attributes: ['is_multiple' => true, 'is_tree' => true]);
        $black = $this->value($colour, 'Black');
        $noir = $this->value($colour, 'Noir');
        $matte = $this->value($colour, 'Matte noir', $noir);
        $this->set($laptops, [$colour]);
        $both = $this->product('Both', $laptops, [$colour->id => [$black, $noir]]);
        $this->product('Only noir', $laptops, [$colour->id => [$noir]]);
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->postJson($this->api("properties/{$colour->id}/values/{$noir->id}/merge"), ['into' => $matte->id])
            ->assertStatus(422);

        $this->actingAs($editor, 'cms')->postJson($this->api("properties/{$colour->id}/values/{$noir->id}/merge"), ['into' => $black->id, 'dry_run' => true])
            ->assertOk()->assertJsonPath('data.moved', 2);
        $this->assertNotNull(PropertyValue::query()->find($noir->id));

        $this->actingAs($editor, 'cms')->postJson($this->api("properties/{$colour->id}/values/{$noir->id}/merge"), ['into' => $black->id])
            ->assertOk()->assertJsonPath('data.moved', 2);

        $this->assertNull(PropertyValue::query()->find($noir->id));
        $this->assertSame([$black->id], DB::table('catalog_product_property_values')->where('product_id', $both->id)->pluck('value_id')->map(static fn ($id): int => (int) $id)->all());
        $this->assertSame(2, DB::table('catalog_product_property_values')->where('value_id', $black->id)->count());
        $this->assertSame($black->id, $matte->refresh()->parent_id);

        $this->get('/laptops/colour_noir')->assertStatus(301)->assertRedirect('/laptops/colour_black');

        $rows = HistoryEntry::query()->where('subject_type', PropertyValue::TYPE)->where('event', '!=', HistoryEntry::CREATED)->get();
        $this->assertCount(1, $rows);
        $this->assertSame($black->id, (int) $rows[0]->subject_id);
        $this->assertSame('Noir, products: 2', collect((array) $rows[0]->changes)->first()['to']);
    }

    #[Test]
    public function a_value_with_products_is_not_deleted_and_one_without_leaves_its_address_without_it(): void
    {
        $laptops = $this->category('laptops');
        $colour = $this->property('Colour');
        $black = $this->value($colour, 'Black');
        $grey = $this->value($colour, 'Grey');
        $this->set($laptops, [$colour]);
        $this->product('Thing', $laptops, [$colour->id => $black]);
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->deleteJson($this->api("properties/{$colour->id}/values/{$black->id}"))
            ->assertStatus(422)
            ->assertJsonPath('meta.products', 1)
            ->assertJsonPath('message', 'Products hold this value — merge it into another instead. Products: 1');

        $this->get('/laptops/colour_grey')->assertOk();
        $this->actingAs($editor, 'cms')->deleteJson($this->api("properties/{$colour->id}/values/{$grey->id}"))->assertNoContent();
        $this->get('/laptops/colour_grey')->assertStatus(301)->assertRedirect('/laptops');
    }

    #[Test]
    public function a_property_in_the_bin_takes_its_facet_and_segment_away_and_keeps_the_values(): void
    {
        $laptops = $this->category('laptops');
        $colour = $this->property('Colour');
        $black = $this->value($colour, 'Black');
        $this->set($laptops, [$colour]);
        $product = $this->product('Thing', $laptops, [$colour->id => $black]);
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->deleteJson($this->api("properties/{$colour->id}"))->assertNoContent();

        $this->assertNull($this->app->make(Facets::class)->find($colour->facetKey()));
        $this->get('/laptops/colour_black')->assertStatus(301)->assertRedirect('/laptops');
        $this->assertSame(1, DB::table('catalog_product_property_values')->where('product_id', $product->id)->count());

        $values = $this->actingAs($editor, 'cms')->getJson($this->api("products/{$product->id}"))->json('data.values');
        $this->assertSame([], $values['properties.values']);

        $this->actingAs($editor, 'cms')->postJson($this->api("properties/{$colour->id}/restore"))->assertOk();

        $this->assertNotNull($this->app->make(Facets::class)->find($colour->facetKey()));
        $this->get('/laptops/colour_black')->assertOk()->assertSee('Thing');
        $values = $this->actingAs($editor, 'cms')->getJson($this->api("products/{$product->id}"))->json('data.values');
        $this->assertSame([$colour->id => $black->id], $values['properties.values']);
    }
}
