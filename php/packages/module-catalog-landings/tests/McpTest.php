<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Catalog\Models\Category;
use WebxUi\CatalogBrands\Models\Brand;
use WebxUi\CatalogLandings\Catalog\LandingGenerator;
use WebxUi\CatalogLandings\Models\Landing;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * §11 of the landings spec (L4): the tools are the catalogue's — its names, scopes and
 * permissions — a set is spoken in codes and slugs and stored by ids, the answer gives both, and
 * the writes go through the panel's form and generator.
 */
final class McpTest extends TestCase
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
        $this->product('MacBook Air', $this->laptops, $this->apple);
        $this->product('MacBook Pro', $this->laptops, $this->apple, ['price' => 900]);
        $this->product('XPS', $this->laptops, $this->dell);
    }

    #[Test]
    public function the_tools_are_the_catalogues_by_name_scope_and_permission(): void
    {
        $registry = $this->app->make(ToolRegistry::class);
        $tools = [];

        foreach ($registry->toolsOf('catalog') as $tool) {
            $tools[$tool->fullName()] = [$tool->scope(), $tool->permissions()];
        }

        foreach (['list', 'get', 'facets', 'count', 'generation'] as $read) {
            $this->assertSame('catalog:read', $tools["catalog_landings_{$read}"][0] ?? null, $read);
            $this->assertContains('catalog.view', $tools["catalog_landings_{$read}"][1], $read);
        }

        foreach (['create', 'update', 'publish', 'unpublish', 'delete', 'restore', 'generate'] as $write) {
            $this->assertSame(['catalog:write', ['catalog.manage']], $tools["catalog_landings_{$write}"] ?? null, $write);
        }

        $this->assertContains('catalog://landings', array_map(static fn (McpResource $resource): string => $resource->uri, $registry->resources()));
        $this->assertSame([], $registry->toolsOf('catalog-landings'));
    }

    #[Test]
    public function a_landing_is_made_from_codes_and_slugs_and_answers_both(): void
    {
        $dry = $this->content($this->agent('catalog_landings_create', ['category' => $this->laptops->id, 'set' => ['brand' => ['apple']], 'dry_run' => true]));
        $this->assertTrue($dry['dry_run']);
        $this->assertSame(0, Landing::query()->count());

        $made = $this->content($this->agent('catalog_landings_create', [
            'category' => $this->laptops->id,
            'set' => ['brand' => ['apple'], 'price' => ['max' => 500]],
            'h1' => 'Apple laptops',
        ]))['landing'];

        // Name and address made from the base and the set when left out.
        $this->assertSame(['en' => 'laptops-apple'], $made['slug']);
        $this->assertEquals(['brand' => ['values' => [(string) $this->apple->id]], 'price' => ['min' => null, 'max' => 500]], $made['filters']);
        $this->assertSame('brand', $made['set'][0]['code']);
        $this->assertSame([['id' => (string) $this->apple->id, 'slug' => 'apple', 'label' => 'Apple']], $made['set'][0]['values']);
        $this->assertSame(1, $made['products_count']);
        $this->assertFalse($made['is_published']);

        // The same set on the same base is refused with the holder's name.
        $this->agent('catalog_landings_create', ['category' => $this->laptops->id, 'set' => ['price' => ['max' => 500], 'brand' => 'apple'], 'slug' => 'other'])
            ->assertHasErrors(['filters']);
        $this->agent('catalog_landings_create', ['set' => ['brand' => ['lenovo']]])->assertHasErrors(['lenovo']);
        $this->agent('catalog_landings_create', ['set' => ['category' => [(string) $this->laptops->id]]])->assertHasErrors();
    }

    #[Test]
    public function an_update_merges_the_languages_and_publish_and_the_bin_work_by_slug(): void
    {
        $landing = $this->landing('laptops-dell', $this->laptops, ['brand' => $this->brands([$this->dell])], ['is_published' => false]);

        $this->agent('catalog_landings_update', ['landing' => 'laptops-dell', 'h1' => 'Dell laptops', 'set' => ['brand' => ['dell', 'apple']]]);
        $landing->refresh();
        $this->assertSame('Dell laptops', $landing->getTranslation('h1', 'en'));
        $this->assertSame(['laptops-dell'], array_values($landing->getTranslations('slug')));
        $this->assertCount(2, $landing->set()->all()['brand']['values']);

        $this->assertTrue($this->content($this->agent('catalog_landings_publish', ['landing' => $landing->id]))['landing']['is_published']);
        $this->agent('catalog_landings_delete', ['landing' => 'laptops-dell']);
        $this->assertSoftDeleted($landing);
        $this->assertSame(1, $this->content($this->agent('catalog_landings_list', ['trashed' => true]))['count']);
        $this->agent('catalog_landings_restore', ['landing' => 'laptops-dell']);
        $this->assertNotSoftDeleted($landing);
    }

    #[Test]
    public function count_and_facets_speak_slugs_and_name_the_holder(): void
    {
        $holder = $this->landing('apple-laptops', $this->laptops, ['brand' => $this->brands([$this->apple])]);

        $count = $this->content($this->agent('catalog_landings_count', ['category' => $this->laptops->id, 'set' => ['brand' => ['apple']]]));
        $this->assertSame(2, $count['count']);
        $this->assertSame($holder->id, $count['taken']['id']);
        $this->assertSame(['en' => 'laptops-apple'], $count['suggested']);

        $facets = $this->content($this->agent('catalog_landings_facets', ['category' => $this->laptops->id]))['facets'];
        $keyed = array_column($facets, null, 'key');
        $this->assertSame(['apple' => 2, 'dell' => 1], array_column($keyed['brand']['values'], 'count', 'slug'));
        $this->assertArrayNotHasKey('category', $keyed);
    }

    #[Test]
    public function generation_previews_with_slugs_and_then_makes_the_free_rows(): void
    {
        $this->landing('laptops-dell', $this->laptops, ['brand' => $this->brands([$this->dell])]);

        $preview = $this->content($this->agent('catalog_landings_generate', [
            'categories' => [$this->laptops->id],
            'facet' => 'brand',
            'h1' => 'Buy {category} {value}',
            'dry_run' => true,
        ]));
        $this->assertSame(2, $preview['total']);
        $this->assertSame(1, $preview['free']);
        $this->assertSame(['apple', 'dell'], array_column($preview['rows'], 'value_slug'));
        $this->assertSame(LandingGenerator::SET_TAKEN, $preview['rows'][1]['conflict']);
        $this->assertSame(1, Landing::query()->count());

        $run = $this->content($this->agent('catalog_landings_generate', ['categories' => [$this->laptops->id], 'facet' => 'brand', 'values' => ['apple', 'dell'], 'h1' => 'Buy {category} {value}']));
        $this->assertSame(1, $run['done']);
        $this->assertSame(1, $run['skipped']);
        $this->assertSame('Buy Laptops Apple', Landing::query()->latest('id')->firstOrFail()->getTranslation('h1', 'en'));

        $this->agent('catalog_landings_generate', ['categories' => [$this->laptops->id], 'facet' => 'price'])->assertHasErrors();
    }

    #[Test]
    public function the_resource_says_the_house_rules_and_the_counts(): void
    {
        $this->landing('laptops-dell', $this->laptops, ['brand' => $this->brands([$this->dell])], ['attention' => Landing::VALUE_REMOVED]);

        $resource = collect($this->app->make(ToolRegistry::class)->resources())->first(static fn (McpResource $one): bool => $one->uri === 'catalog://landings');
        $this->assertInstanceOf(McpResource::class, $resource);

        $read = ($resource->handler)();
        $this->assertArrayHasKey('duplicates', $read['rules']);
        $this->assertSame(1, $read['counts']['all']);
        $this->assertSame(1, $read['counts']['attention']);
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
