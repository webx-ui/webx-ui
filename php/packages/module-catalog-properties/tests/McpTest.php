<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\CatalogProperties\Catalog\ProductValues;
use WebxUi\CatalogProperties\Models\Property;
use WebxUi\CatalogProperties\Models\PropertyGroup;
use WebxUi\CatalogProperties\Models\PropertyValue;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * §10 of the properties spec (P5): the tools are the catalogue's — its names, scopes and
 * permissions — and go through the panel's doors; a value of a product is an id or a slug in any
 * language, and an unknown slug says how to make one.
 */
final class McpTest extends TestCase
{
    #[Test]
    public function the_tools_are_the_catalogues_by_name_scope_and_permission(): void
    {
        $registry = $this->app->make(ToolRegistry::class);
        $tools = [];

        foreach ($registry->toolsOf('catalog') as $tool) {
            $tools[$tool->fullName()] = [$tool->scope(), $tool->permissions()];
        }

        foreach ([
            'catalog_properties_list', 'catalog_properties_get', 'catalog_property_values_list',
            'catalog_property_groups_list', 'catalog_categories_properties',
        ] as $read) {
            $this->assertSame('catalog:read', $tools[$read][0] ?? null, $read);
            $this->assertContains('catalog.view', $tools[$read][1], $read);
        }

        foreach ([
            'catalog_properties_create', 'catalog_properties_update', 'catalog_properties_delete', 'catalog_properties_restore',
            'catalog_properties_reorder', 'catalog_property_values_create', 'catalog_property_values_update',
            'catalog_property_values_move', 'catalog_property_values_merge', 'catalog_property_values_delete',
            'catalog_property_groups_create', 'catalog_property_groups_update', 'catalog_property_groups_delete',
            'catalog_property_groups_reorder', 'catalog_categories_properties_set',
        ] as $write) {
            $this->assertSame(['catalog:write', ['catalog.manage']], $tools[$write] ?? null, $write);
        }

        $this->assertContains('catalog://properties', array_map(static fn (McpResource $resource): string => $resource->uri, $registry->resources()));
        $this->assertSame([], $registry->toolsOf('catalog-properties'));
    }

    #[Test]
    public function a_property_is_made_through_the_form_and_a_dry_run_leaves_nothing(): void
    {
        $answer = $this->content($this->agent('catalog_properties_create', ['title' => 'Kit', 'type' => 'text', 'values' => ['is_filterable' => true], 'dry_run' => true]));
        $this->assertTrue($answer['dry_run']);
        $this->assertSame('kit', $answer['would_be']['property']['code']['en']);
        $this->assertSame(0, Property::query()->count());
        $this->assertSame(0, $this->content($this->agent('catalog_properties_list'))['count']);

        $made = $this->content($this->agent('catalog_properties_create', ['title' => 'Kit', 'type' => 'text', 'values' => ['is_filterable' => true]]));

        // A text is never a filter (decision 4): the flag is put out, not refused.
        $this->assertFalse($made['property']['is_filterable']);
        $this->assertSame(1, $this->content($this->agent('catalog_properties_list'))['count']);

        $this->agent('catalog_properties_create', ['title' => 'Kit', 'type' => 'select'])->assertHasErrors(['code']);
        $this->agent('catalog_properties_update', ['property' => 'kit', 'values' => ['type' => 'number']])->assertHasErrors(['type']);
    }

    #[Test]
    public function the_intervals_of_a_number_are_the_whole_table_and_only_a_numbers(): void
    {
        $weight = $this->property('Weight', Property::NUMBER, ['filter_mode' => Property::INTERVALS]);
        $colour = $this->property('Colour');

        $answer = $this->content($this->agent('catalog_properties_update', ['property' => $weight->id, 'intervals' => [
            ['title' => 'Light', 'max' => 1],
            ['title' => 'Heavy', 'min' => 1],
        ]]));

        $this->assertSame(['Light', 'Heavy'], array_map(static fn (array $row): string => $row['title']['en'], $answer['intervals']));
        $this->assertNull($answer['intervals'][0]['min']);

        $this->agent('catalog_properties_update', ['property' => $weight->id, 'intervals' => [['title' => 'Odd', 'min' => 2, 'max' => 1]]])
            ->assertHasErrors(['intervals.0.max']);
        $this->agent('catalog_properties_update', ['property' => $colour->id, 'intervals' => []])->assertHasErrors(['Only a number']);
    }

    #[Test]
    public function values_are_found_by_slug_and_a_search_gives_the_path(): void
    {
        $material = $this->property('Material', attributes: ['is_tree' => true]);
        $metal = $this->value($material, 'Metal');

        $steel = $this->content($this->agent('catalog_property_values_create', ['property' => $material->id, 'title' => 'Steel', 'parent' => 'metal']));
        $this->assertSame($metal->id, $steel['value']['parent_id']);
        $this->assertSame('steel', $steel['value']['slug']['en']);

        $found = $this->content($this->agent('catalog_property_values_list', ['property' => 'material', 'search' => 'ste']));
        $this->assertSame(1, $found['count']);
        $this->assertSame(['Metal'], $found['values'][0]['path']);

        $top = $this->content($this->agent('catalog_property_values_list', ['property' => $material->id]));
        $this->assertSame(['Metal'], array_map(static fn (array $one): string => $one['title']['en'], $top['values']));

        $this->agent('catalog_property_values_update', ['property' => $material->id, 'value' => 'nope', 'values' => ['title' => 'X']])
            ->assertHasErrors(['catalog_property_values_create']);
        $this->agent('catalog_property_values_list', ['property' => $this->property('Weight', Property::NUMBER)->id])->assertHasErrors(['only a select']);
    }

    #[Test]
    public function merge_counts_on_a_dry_run_and_delete_of_a_held_value_says_merge(): void
    {
        $colour = $this->property('Colour');
        $black = $this->value($colour, 'Black');
        $noir = $this->value($colour, 'Noir');
        $shoes = $this->category('shoes');
        $this->set($shoes, [$colour]);
        $this->product('Boot', $shoes, [$colour->id => $noir]);
        $this->product('Shoe', $shoes, [$colour->id => $noir]);

        $this->agent('catalog_property_values_delete', ['property' => $colour->id, 'value' => 'noir'])->assertHasErrors(['Products: 2', 'property_values_merge']);

        $dry = $this->content($this->agent('catalog_property_values_merge', ['property' => $colour->id, 'value' => 'noir', 'into' => 'black', 'dry_run' => true]));
        $this->assertSame(2, $dry['would_move']);
        $this->assertNotNull(PropertyValue::query()->find($noir->id));

        $merged = $this->content($this->agent('catalog_property_values_merge', ['property' => $colour->id, 'value' => $noir->id, 'into' => $black->id]));
        $this->assertSame(2, $merged['moved']);
        $this->assertNull(PropertyValue::query()->find($noir->id));
        $this->assertSame(2, DB::table(ProductValues::TABLE)->where('value_id', $black->id)->count());

        $this->agent('catalog_property_values_delete', ['property' => $colour->id, 'value' => $this->value($colour, 'Grey')->id])->assertOk();
    }

    #[Test]
    public function a_set_is_read_with_where_it_comes_from_and_an_inherited_property_is_refused(): void
    {
        $colour = $this->property('Colour');
        $weight = $this->property('Weight', Property::NUMBER);
        $root = $this->category('goods');
        $shoes = $this->category('shoes', $root);
        $this->set($root, [$colour]);

        $this->agent('catalog_categories_properties_set', ['category' => $shoes->id, 'ids' => [$colour->id]])->assertHasErrors(['Goods']);

        $set = $this->content($this->agent('catalog_categories_properties_set', ['category' => $shoes->id, 'ids' => [$weight->id]]));
        $this->assertSame([$colour->id, $weight->id], $set['effective']);
        $this->assertSame($root->id, $set['inherited'][0]['from']['id']);
        $this->assertSame([$weight->id], array_column($set['own'], 'id'));

        $this->assertSame([$shoes->id], array_column($this->content($this->agent('catalog_properties_get', ['property' => $weight->id]))['added_by'], 'id'));
    }

    #[Test]
    public function a_product_takes_a_value_by_slug_and_an_unknown_slug_says_how_to_make_one(): void
    {
        $colour = $this->property('Colour', attributes: ['is_multiple' => true]);
        $black = $this->value($colour, 'Black');
        $grey = $this->value($colour, 'Grey');
        $shoes = $this->category('shoes');
        $this->set($shoes, [$colour]);
        $boot = $this->product('Boot', $shoes);

        $this->agent('catalog_products_update', ['product' => $boot->id, 'values' => ['properties.values' => [(string) $colour->id => ['black', $grey->id]]]])->assertOk();
        $this->assertEqualsCanonicalizing([$black->id, $grey->id], DB::table(ProductValues::TABLE)->where('product_id', $boot->id)->pluck('value_id')->map(static fn (mixed $id): int => (int) $id)->all());

        $this->agent('catalog_products_update', ['product' => $boot->id, 'values' => ['properties.values' => [(string) $colour->id => 'white']]])
            ->assertHasErrors(['«white»', 'catalog_property_values_create']);
    }

    #[Test]
    public function the_groups_are_the_shared_category_tools_under_their_own_names(): void
    {
        $this->agent('catalog_property_groups_create', ['title' => 'Size'])->assertOk();
        $group = PropertyGroup::query()->firstOrFail();
        $this->property('Width', Property::NUMBER, ['group_id' => $group->id]);

        $list = $this->content($this->agent('catalog_property_groups_list'));
        $this->assertSame(1, $list['groups'][0]['properties_count']);

        $this->agent('catalog_property_groups_delete', ['group' => 'nope'])->assertHasErrors(['catalog_property_groups_list']);
    }

    #[Test]
    public function the_resource_says_what_each_type_keeps(): void
    {
        $this->property('Colour', attributes: ['code' => ['en' => 'colour']]);
        $this->property('Kit', Property::TEXT);

        $resource = collect($this->app->make(ToolRegistry::class)->resources())->first(static fn (McpResource $one): bool => $one->uri === 'catalog://properties');
        $this->assertInstanceOf(McpResource::class, $resource);
        $read = ($resource->handler)();

        $this->assertNotContains('is_filterable', $read['types']['text']['flags']);
        $this->assertContains('has_color', $read['types']['select']['flags']);
        $this->assertSame(['en' => 'colour'], $read['properties'][0]['code']);
        $this->assertSame('text', $read['properties'][1]['type']);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = [], ?CmsUser $as = null): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($as ?? $this->editor(), 'cms')->tool($bound, $arguments);
    }

    /**
     * @return array<string, mixed>
     */
    private function content(TestResponse $response): array
    {
        $decoded = null;

        $response->assertStructuredContent(static function (AssertableJson $json) use (&$decoded): void {
            $decoded = $json->etc()->toArray();
        });

        return is_array($decoded) ? $decoded : [];
    }
}
