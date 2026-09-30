<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Contracts\HasNavSection;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Panel\CatalogModule;
use WebxUi\CatalogProperties\Models\Property;

/**
 * §7 of the properties spec as the panel's section needs it (P4): the entry of the navigation, the
 * two tabs patched onto the catalogue's screens, the properties and the values asked by id — the
 * product form names what it holds outside the set, and a tree picker opens to a value by its
 * path — and the columns of the list of products, which live in the database.
 */
final class PanelTest extends TestCase
{
    #[Test]
    public function the_section_stands_under_dictionaries_after_the_labels_and_the_stock(): void
    {
        $module = $this->app->make(ModuleRegistry::class)->get('catalog-properties');

        $this->assertSame(312, $module->order());
        $this->assertSame(CatalogModule::GROUP, $module->group());
        $this->assertInstanceOf(HasNavSection::class, $module);
        $this->assertSame(CatalogModule::DICTIONARIES, $module->navSection());
    }

    #[Test]
    public function the_category_form_gets_the_set_and_the_product_form_the_specifications(): void
    {
        $screens = $this->app->make(ScreenRegistry::class);

        $this->assertSame('wx-catalog-category-properties', $this->find($screens->tree(Category::SCREEN), 'category-properties')['type'] ?? null);

        $values = $this->find($screens->tree(Product::SCREEN), 'properties-values');
        $this->assertSame('wx-catalog-product-properties', $values['type'] ?? null);
        $this->assertSame('properties.values', $values['name'] ?? null);
    }

    #[Test]
    public function properties_and_values_are_read_by_id_and_a_value_with_its_path(): void
    {
        $colour = $this->property('Colour');
        $weight = $this->property('Weight', Property::NUMBER);
        $material = $this->property('Material', attributes: ['is_tree' => true]);
        $metal = $this->value($material, 'Metal');
        $steel = $this->value($material, 'Steel', $metal);
        $stainless = $this->value($material, 'Stainless', $steel);
        $editor = $this->editor(['catalog.view']);

        $this->actingAs($editor, 'cms')->getJson($this->api("properties?ids[]={$colour->id}&ids[]={$weight->id}"))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $colour->id)
            ->assertJsonPath('data.1.id', $weight->id);

        $this->actingAs($editor, 'cms')->getJson($this->api("properties/{$material->id}/values?ids[]={$stainless->id}"))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $stainless->id)
            ->assertJsonPath('data.0.ancestors.0.id', $metal->id)
            ->assertJsonPath('data.0.ancestors.1.id', $steel->id)
            ->assertJsonCount(2, 'data.0.ancestors');
    }

    #[Test]
    public function a_property_marked_for_the_list_is_a_column_of_words_inside_the_set_only(): void
    {
        $colour = $this->property('Colour', attributes: ['in_list' => true, 'is_multiple' => true, 'value_order' => Property::MANUAL]);
        $diagonal = $this->property('Diagonal', Property::NUMBER, ['in_list' => true, 'unit_prefix' => ['en' => '⌀'], 'unit_suffix' => ['en' => ' mm']]);
        $hidden = $this->property('Weight', Property::NUMBER);
        $black = $this->value($colour, 'Black');
        $grey = $this->value($colour, 'Grey');

        $laptops = $this->category('laptops');
        $cables = $this->category('cables');
        $this->set($laptops, [$colour, $diagonal, $hidden]);
        $this->set($cables, [$diagonal]);

        $laptop = $this->product('Laptop', $laptops, [$colour->id => [$black, $grey], $diagonal->id => 12, $hidden->id => 2]);
        // Its colour is outside the set of its category: kept, and shown nowhere (decision 6).
        $cable = $this->product('Cable', $cables, [$colour->id => [$black], $diagonal->id => 3]);

        $answer = $this->actingAs($this->editor(), 'cms')->getJson($this->api('products?per_page=50'))->assertOk();

        $columns = collect((array) $answer->json('columns'))->keyBy('key');
        $this->assertSame('Colour', $columns->get("p.{$colour->id}")['label'] ?? null);
        $this->assertSame('Diagonal', $columns->get("p.{$diagonal->id}")['label'] ?? null);
        $this->assertFalse($columns->has("p.{$hidden->id}"));

        $rows = collect((array) $answer->json('data'))->keyBy('id');
        $this->assertSame('Black, Grey', $rows[$laptop->id]['columns']["p.{$colour->id}"]);
        $this->assertSame('⌀12 mm', $rows[$laptop->id]['columns']["p.{$diagonal->id}"]);
        $this->assertNull($rows[$cable->id]['columns']["p.{$colour->id}"]);
        $this->assertSame('⌀3 mm', $rows[$cable->id]['columns']["p.{$diagonal->id}"]);
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, mixed>|null
     */
    private function find(array $nodes, string $id): ?array
    {
        foreach ($nodes as $node) {
            if (($node['id'] ?? null) === $id) {
                return $node;
            }

            $found = $this->find((array) ($node['children'] ?? []), $id);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
