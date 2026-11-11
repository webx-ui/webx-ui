<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Testing\AssertsPermissions;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogBrands\BrandsServiceProvider;
use WebxUi\CatalogBrands\Models\Brand;
use WebxUi\CatalogCollections\Catalog\CollectionPreview;
use WebxUi\CatalogCollections\Models\ProductCollection;
use WebxUi\CatalogLabels\LabelsServiceProvider;
use WebxUi\CatalogLabels\Models\Label;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * §7 of the collections spec: the tools are the catalogue's by name, scope and permission, a set is
 * spoken in codes and slugs and stored by ids, a `dry_run` writes nothing, and every refusal is a
 * {@see ToolFailure} an agent can read.
 */
final class McpTest extends TestCase
{
    use AssertsPermissions;

    private const READS = ['list', 'get', 'facets', 'preview', 'usage'];

    private const WRITES = ['create', 'update', 'products', 'delete', 'restore', 'purge'];

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), LabelsServiceProvider::class, BrandsServiceProvider::class];
    }

    #[Test]
    public function the_tools_are_the_catalogues_by_name_scope_and_permission(): void
    {
        $registry = $this->app->make(ToolRegistry::class);
        $tools = [];

        foreach ($registry->toolsOf('catalog') as $tool) {
            $tools[$tool->fullName()] = [$tool->scope(), $tool->permissions(), $tool->tool->mutating];
        }

        foreach (self::READS as $read) {
            $this->assertSame(['catalog:read', ['catalog.view', 'catalog.manage'], false], $tools["catalog_collections_{$read}"] ?? null, $read);
        }

        foreach (self::WRITES as $write) {
            $this->assertSame(['catalog:write', ['catalog.manage'], true], $tools["catalog_collections_{$write}"] ?? null, $write);
        }

        $this->assertContains('catalog://collections', array_map(static fn (McpResource $resource): string => $resource->uri, $registry->resources()));
        $this->assertSame([], $registry->toolsOf('catalog-collections'));
    }

    #[Test]
    public function nobody_without_a_permission_reaches_a_tool(): void
    {
        $this->assertToolsNeedPermission('catalog');
    }

    #[Test]
    public function a_reader_reads_every_tool_of_reading_and_writes_with_none(): void
    {
        $collection = $this->manual('picks');
        $reader = $this->manager(['catalog.view']);

        foreach (self::READS as $read) {
            $this->agent("catalog_collections_{$read}", ['collection' => 'picks'], $reader)->assertOk();
        }

        $writes = [
            'create' => ['values' => ['title' => 'Other']],
            'update' => ['collection' => 'picks', 'values' => ['title' => 'Other']],
            'products' => ['collection' => 'picks', 'set' => []],
            'delete' => ['collection' => 'picks'],
            'restore' => ['collection' => 'picks'],
            'purge' => ['collection' => 'picks'],
        ];

        foreach ($writes as $write => $arguments) {
            $this->agent("catalog_collections_{$write}", $arguments, $reader)->assertHasErrors(['[catalog.manage]']);
        }

        $this->assertSame('Picks', $collection->refresh()->label('en'));
        $this->assertSame(1, ProductCollection::query()->count());
    }

    #[Test]
    public function no_write_is_kept_on_a_dry_run(): void
    {
        $collection = $this->manual('picks');
        $product = $this->product('Camera');
        $bin = $this->manual('old');
        $bin->delete();
        $before = $this->tables();

        $made = $this->content($this->agent('catalog_collections_create', ['values' => ['title' => 'Fresh'], 'dry_run' => true]));
        $this->assertTrue($made['dry_run']);
        $this->assertSame('fresh', $made['would_be']['collection']['key']);

        $changed = $this->content($this->agent('catalog_collections_update', ['collection' => 'picks', 'values' => ['title' => 'Other', 'limit' => 4], 'dry_run' => true]));
        $this->assertSame(4, $changed['would_be']['collection']['limit']);

        $listed = $this->content($this->agent('catalog_collections_products', ['collection' => 'picks', 'add' => [$product->id], 'dry_run' => true]));
        $this->assertSame([$product->id], $listed['would_be']['collection']['products']);

        $gone = $this->content($this->agent('catalog_collections_delete', ['collection' => 'picks', 'dry_run' => true]));
        $this->assertSame('picks', $gone['would_be']['key']);

        $back = $this->content($this->agent('catalog_collections_restore', ['collection' => 'old', 'dry_run' => true]));
        $this->assertNull($back['would_be']['collection']['deleted_at']);

        $purged = $this->content($this->agent('catalog_collections_purge', ['collection' => 'old', 'dry_run' => true]));
        $this->assertTrue($purged['dry_run']);
        $this->assertSame('old', $purged['would_be']['key']);
        $this->assertArrayHasKey('places', $purged['would_be']);

        $this->assertSame($before, $this->tables());
        $this->assertNotSoftDeleted($collection);
        $this->assertSoftDeleted($bin);

        // A refusal is the one the real write would meet.
        $this->agent('catalog_collections_update', ['collection' => 'picks', 'values' => ['limit' => 49], 'dry_run' => true])->assertHasErrors(['limit', '48']);

        // The same call without it does write, the journal included: the comparison above can fail.
        $this->agent('catalog_collections_update', ['collection' => 'picks', 'values' => ['title' => 'Other']])->assertOk();
        $this->assertNotSame($before['journal'], $this->tables()['journal']);
        $this->assertSame('Other', $collection->refresh()->label('en'));
    }

    #[Test]
    public function a_collection_is_made_from_a_preset_and_answers_in_codes_and_ids(): void
    {
        $hit = $this->label('Hit');

        $made = $this->content($this->agent('catalog_collections_create', [
            'preset' => 'label',
            'values' => ['title' => 'Hits', 'set' => ['label' => ['hit']], 'limit' => 4],
        ]))['collection'];

        $this->assertSame('hits', $made['key']);
        $this->assertSame('filter', $made['source']);
        $this->assertSame('label', $made['preset']);
        $this->assertEquals(['label' => ['values' => [(string) $hit->id]]], (array) $made['filters']);
        $this->assertSame('label', $made['set'][0]['code']);
        $this->assertSame([['id' => (string) $hit->id, 'slug' => 'hit', 'label' => 'Hit']], $made['set'][0]['values']);

        $new = $this->content($this->agent('catalog_collections_create', ['preset' => 'new', 'values' => ['title' => 'New arrivals']]))['collection'];
        $this->assertSame('new', $new['sort']);
        $this->assertSame('new', $new['preset']);

        $sale = $this->content($this->agent('catalog_collections_create', ['preset' => 'on-sale', 'values' => ['title' => 'Sale', 'view' => 'carousel']]))['collection'];
        $this->assertTrue($sale['on_sale']);
        $this->assertSame('carousel', $sale['view']);
    }

    #[Test]
    public function an_update_changes_what_is_sent_and_merges_the_languages(): void
    {
        $collection = ProductCollection::query()->create([
            'key' => 'picks',
            'title' => ['en' => 'Picks', 'ru' => 'Подборка'],
            'attention' => ProductCollection::VALUE_DELETED,
        ]);

        $saved = $this->content($this->agent('catalog_collections_update', [
            'collection' => $collection->id,
            'values' => ['title' => 'Our picks', 'link_text' => ['ru' => 'Все'], 'key' => 'picks'],
        ]))['collection'];

        $this->assertSame(['en' => 'Our picks', 'ru' => 'Подборка'], $saved['title']);
        $this->assertSame(['ru' => 'Все'], $saved['link_text']);
        $this->assertNull($saved['attention']);
        $this->assertSame(8, $saved['limit']);
    }

    #[Test]
    public function the_list_is_written_whole_or_by_changes_and_keeps_a_row_in_the_bin(): void
    {
        [$one, $two, $three] = [$this->product('One'), $this->product('Two'), $this->product('Three')];
        $this->manual('picks');

        $this->assertSame([$one->id, $two->id], $this->listed(['set' => [$one->id, $two->id, $one->id]]));
        $this->assertSame([$two->id, $three->id], $this->listed(['remove' => [$one->id], 'add' => [$three->id, $two->id]]));
        $this->assertSame([$three->id, $two->id], $this->listed(['move' => [['product' => $three->id, 'position' => 1]]]));

        // A product gone to the bin while on the list waits there: reordering around it is no addition.
        $two->delete();
        $this->assertSame([$two->id, $three->id], $this->listed(['set' => [$two->id, $three->id]]));
    }

    #[Test]
    public function the_bin_works_by_key_and_checks_no_places(): void
    {
        $collection = $this->manual('picks');

        $this->assertSame('picks', $this->content($this->agent('catalog_collections_delete', ['collection' => 'picks']))['key']);
        $this->assertSoftDeleted($collection);
        $this->assertSame(0, $this->content($this->agent('catalog_collections_list'))['count']);
        $this->assertSame('picks', $this->content($this->agent('catalog_collections_list', ['trashed' => true]))['collections'][0]['key']);
        $this->assertSame('picks', $this->content($this->agent('catalog_collections_get', ['collection' => 'picks']))['collection']['key']);

        $this->agent('catalog_collections_restore', ['collection' => $collection->id]);
        $this->assertNotSoftDeleted($collection);
    }

    #[Test]
    public function a_collection_is_purged_only_from_the_bin_and_its_key_is_free_after_it(): void
    {
        $collection = $this->manual('picks');

        $this->agent('catalog_collections_purge', ['collection' => 'picks'])->assertHasErrors(['not in the bin']);
        $this->assertNotSoftDeleted($collection);

        $collection->delete();

        $answer = $this->content($this->agent('catalog_collections_purge', ['collection' => 'picks']));
        $this->assertSame($collection->id, $answer['purged']);
        $this->assertSame('picks', $answer['key']);
        $this->assertSame(0, ProductCollection::withTrashed()->count());

        $made = $this->content($this->agent('catalog_collections_create', ['values' => ['title' => 'Again', 'key' => 'picks']]));
        $this->assertSame('picks', $made['collection']['key']);
    }

    #[Test]
    public function a_key_of_digits_is_a_key_before_it_is_an_id(): void
    {
        $this->manual('other');
        $year = $this->manual('2026');

        $this->assertSame((int) $year->id, $this->content($this->agent('catalog_collections_get', ['collection' => '2026']))['collection']['id']);
    }

    #[Test]
    public function the_list_finds_by_title_and_state_and_says_nothing_was_counted_without_blocks(): void
    {
        $this->manual('picks');
        $this->manual('off', ['title' => 'Switched off', 'is_enabled' => false, 'attention' => ProductCollection::VALUE_DELETED]);

        $this->assertSame(1, $this->content($this->agent('catalog_collections_list', ['search' => 'pic']))['count']);
        $this->assertSame('off', $this->content($this->agent('catalog_collections_list', ['enabled' => false]))['collections'][0]['key']);
        $this->assertSame('off', $this->content($this->agent('catalog_collections_list', ['attention' => true]))['collections'][0]['key']);

        $list = $this->content($this->agent('catalog_collections_list'));
        $this->assertSame(2, $list['count']);
        $this->assertNull($list['collections'][0]['places']);

        $usage = $this->content($this->agent('catalog_collections_usage', ['collection' => 'picks']));
        $this->assertNull($usage['places']);
        $this->assertStringContainsString('shortcode', $usage['not_counted']);
    }

    #[Test]
    public function the_facets_give_the_words_of_a_set_the_presets_and_the_sorts(): void
    {
        $brand = Brand::query()->create(['title' => 'Acme', 'slug' => 'acme', 'is_visible' => true]);
        $product = $this->product('Camera');
        DB::table(Brand::LINKS)->insert(['product_id' => $product->id, 'brand_id' => $brand->id]);

        $answer = $this->content($this->agent('catalog_collections_facets'));
        $keyed = array_column($answer['facets'], null, 'key');

        $this->assertSame(['acme' => 1], array_column($keyed['brand']['values'], 'count', 'slug'));
        $this->assertArrayNotHasKey('category', $keyed);
        $this->assertSame(['new', 'on-sale', 'label'], array_column($answer['presets'], 'key'));
        $this->assertContains('new', array_column($answer['sorts'], 'key'));
    }

    #[Test]
    public function a_preview_answers_on_a_collection_on_values_and_on_both(): void
    {
        $shown = $this->product('Shown');
        $hidden = $this->product('Hidden', ['is_published' => false]);
        $collection = $this->manual('picks', ['limit' => 1]);
        $collection->products()->attach($shown->id, ['position' => 1]);
        $collection->products()->attach($hidden->id, ['position' => 2]);
        $before = $this->tables();

        $saved = $this->content($this->agent('catalog_collections_preview', ['collection' => 'picks']));
        $this->assertSame([$shown->id], array_column($saved['products'], 'id'));
        $this->assertSame(CollectionPreview::UNPUBLISHED, $saved['dropped'][0]['reason']);

        $values = $this->content($this->agent('catalog_collections_preview', ['values' => ['source' => 'filter', 'title' => 'Ignored', 'limit' => 5]]));
        $this->assertSame([$shown->id], array_column($values['products'], 'id'));

        $over = $this->content($this->agent('catalog_collections_preview', ['collection' => 'picks', 'values' => ['products' => [$hidden->id]]]));
        $this->assertSame([], $over['products']);

        $this->assertArrayNotHasKey('dry_run', $this->app->make(ToolRegistry::class)->tool('catalog_collections_preview')->tool->inputSchema['properties']);
        $this->assertSame($before, $this->tables());
    }

    #[Test]
    public function a_switch_takes_what_a_switch_has_always_taken(): void
    {
        $this->manual('sale', ['source' => ProductCollection::FILTER]);

        foreach ([[true, true], [false, false], [1, true], [0, false], ['1', true], ['0', false]] as [$sent, $kept]) {
            foreach (['on_sale', 'only_purchasable', 'is_enabled'] as $field) {
                $this->agent('catalog_collections_update', ['collection' => 'sale', 'values' => [$field => $sent]])->assertOk();

                $this->assertSame($kept, ProductCollection::query()->where('key', 'sale')->sole()->{$field}, $field);
            }
        }
    }

    #[Test]
    public function every_refusal_is_a_failure_an_agent_can_read(): void
    {
        $this->manual('picks');
        $filter = $this->manual('sale', ['source' => ProductCollection::FILTER, 'on_sale' => true]);
        $binned = $this->manual('old');
        $binned->delete();
        $product = $this->product('Camera');
        $gone = $this->product('Gone');
        $gone->delete();
        $category = new Category(['name' => 'Parts', 'slug' => 'kept', 'is_published' => true]);
        $category->saveAsRoot();

        $refusals = [
            // Who: an unknown key names the keys there are.
            ['catalog_collections_get', ['collection' => 'mystery'], 'There are: picks, sale'],
            ['catalog_collections_get', [], 'There is no collection'],
            ['catalog_collections_usage', ['collection' => 'mystery'], 'There are: picks, sale'],
            ['catalog_collections_update', ['collection' => 'old', 'values' => ['limit' => 4]], 'restored first'],
            ['catalog_collections_delete', ['collection' => 'mystery'], 'There are: picks, sale'],
            ['catalog_collections_restore', ['collection' => 'mystery'], 'There are: picks, sale'],
            ['catalog_collections_restore', ['collection' => 'picks'], 'not in the bin'],
            ['catalog_collections_purge', ['collection' => 'mystery'], 'There are: picks, sale'],
            ['catalog_collections_products', ['collection' => 'mystery', 'set' => []], 'There are: picks, sale'],

            // The call's own words.
            ['catalog_collections_get', ['collection' => 'picks', 'locale' => 'xx'], 'no language "xx"'],
            ['catalog_collections_facets', ['category' => 999999], 'no category "999999"'],
            ['catalog_collections_facets', ['category' => 'parts'], 'no category "parts"'],
            ['catalog_collections_create', ['values' => 'Picks'], '`values`'],
            ['catalog_collections_create', ['values' => ['Picks']], '`values`'],
            ['catalog_collections_create', ['values' => ['title' => 'A', 'mystery' => 1]], 'no field "mystery"'],
            ['catalog_collections_create', ['values' => ['title' => 'A', 'category_id' => 1]], 'no field "category_id"'],
            ['catalog_collections_create', ['values' => ['title' => 5]], '`title`'],
            ['catalog_collections_create', ['values' => ['title' => ['xx' => 'A']]], 'no language "xx"'],
            ['catalog_collections_create', ['values' => ['title' => 'A', 'products' => [$product->id]]], 'catalog_collections_products'],
            ['catalog_collections_update', ['collection' => 'picks', 'values' => []], 'Send what changes'],

            // The model's and the form's walls, in the panel's words.
            ['catalog_collections_create', ['values' => []], 'Give the collection a title'],
            ['catalog_collections_create', ['values' => ['title' => 'A', 'key' => 'picks']], 'key: The key «picks» is taken'],
            ['catalog_collections_create', ['values' => ['title' => 'A', 'key' => 'old']], 'perhaps by a collection in the bin'],
            // Brought to `picks` first (#125): taken, not misshapen.
            ['catalog_collections_create', ['values' => ['title' => 'A', 'key' => 'Picks!']], 'key: The key «picks» is taken'],
            ['catalog_collections_create', ['values' => ['title' => 'A', 'limit' => 49]], 'limit: The limit is a number from 1 to 48'],
            ['catalog_collections_create', ['values' => ['title' => 'A', 'limit' => 'many']], 'from 1 to 48'],
            ['catalog_collections_create', ['values' => ['title' => 'A', 'view' => 'list']], 'no view called «list»'],
            ['catalog_collections_create', ['values' => ['title' => 'A', 'view' => ['grid']]], 'view'],
            ['catalog_collections_create', ['values' => ['title' => 'A', 'source' => 'mixed']], 'called «mixed»'],
            ['catalog_collections_create', ['values' => ['title' => 'A', 'link' => ['target' => 'url', 'url' => 'javascript:alert(1)']]], 'link'],
            ['catalog_collections_create', ['values' => ['title' => 'A', 'source' => 'filter', 'sort' => 'mystery']], 'no sort «mystery»'],
            ['catalog_collections_update', ['collection' => 'picks', 'values' => ['key' => 'other']], 'templates and texts already name it'],

            // The set: what is unknown points at the tool that knows.
            ['catalog_collections_create', ['values' => ['title' => 'A', 'source' => 'filter', 'set' => ['mystery' => ['a']]]], 'no facet "mystery". catalog_collections_facets'],
            ['catalog_collections_create', ['values' => ['title' => 'A', 'source' => 'filter', 'set' => ['brand' => ['mystery']]]], 'no value "mystery". catalog_collections_facets'],
            ['catalog_collections_create', ['values' => ['title' => 'A', 'source' => 'filter', 'set' => ['brand' => [['a']]]]], 'no value'],
            ['catalog_collections_create', ['values' => ['title' => 'A', 'source' => 'filter', 'set' => ['brand']]], '`set`'],
            ['catalog_collections_create', ['values' => ['title' => 'A', 'source' => 'filter', 'set' => ['price' => 5]]], 'is a range'],
            ['catalog_collections_create', ['values' => ['title' => 'A', 'source' => 'filter', 'set' => ['category' => ['parts']]]], 'the collection’s own field'],

            // What would be written and never read.
            ['catalog_collections_create', ['values' => ['title' => 'A', 'set' => ['price' => ['max' => 5]]]], 'does not read them'],
            ['catalog_collections_create', ['values' => ['title' => 'A', 'on_sale' => true, 'sort' => 'new']], 'on_sale, sort'],
            ['catalog_collections_create', ['values' => ['title' => 'A', 'category' => $category->id]], 'category:'],
            ['catalog_collections_create', ['preset' => 'new', 'values' => ['title' => 'A', 'source' => 'manual']], 'sort:'],
            ['catalog_collections_update', ['collection' => 'picks', 'values' => ['sort' => 'new']], 'sort:'],

            // What would break a page, or be read as something else.
            ['catalog_collections_create', ['values' => ['title' => 'A', 'link_text' => ['en' => ['x']]]], '`link_text` in "en"'],
            ['catalog_collections_create', ['values' => ['title' => 'A', 'link_text' => ['en' => 5]]], '`link_text` in "en"'],
            ['catalog_collections_create', ['values' => ['title' => ['en' => 'A', 'ru' => ['x']]]], '`title` in "ru"'],
            ['catalog_collections_update', ['collection' => 'picks', 'values' => ['link_text' => ['en' => ['x']]]], '`link_text` in "en"'],
            ['catalog_collections_preview', ['values' => ['link_text' => ['en' => ['x']]]], '`link_text` in "en"'],
            ['catalog_collections_create', ['values' => ['title' => 'A', 'limit' => [1]]], 'limit: The limit is a number from 1 to 48'],
            ['catalog_collections_create', ['values' => ['title' => 'A', 'limit' => [1]], 'dry_run' => true], 'from 1 to 48'],
            ['catalog_collections_create', ['values' => ['title' => 'A', 'limit' => 1.5]], 'from 1 to 48'],
            ['catalog_collections_create', ['values' => ['title' => 'A', 'limit' => true]], 'from 1 to 48'],
            ['catalog_collections_update', ['collection' => 'picks', 'values' => ['limit' => [1]]], 'from 1 to 48'],
            ['catalog_collections_update', ['collection' => 'picks', 'values' => ['limit' => 1.5]], 'from 1 to 48'],

            // Presets.
            ['catalog_collections_create', ['preset' => 'mystery', 'values' => ['title' => 'A']], 'no preset "mystery": new, on-sale, label'],
            ['catalog_collections_create', ['preset' => ['new'], 'values' => ['title' => 'A']], 'no preset'],
            ['catalog_collections_create', ['preset' => 'label', 'values' => ['title' => 'A']], 'needs its values in values.set'],

            // The list.
            ['catalog_collections_products', ['collection' => 'sale', 'set' => [$product->id]], 'reads no hand-picked list'],
            ['catalog_collections_products', ['collection' => 'picks'], 'Send the whole list'],
            ['catalog_collections_products', ['collection' => 'picks', 'set' => [], 'add' => [$product->id]], 'send it alone'],
            ['catalog_collections_products', ['collection' => 'picks', 'set' => 5], '`set`'],
            ['catalog_collections_products', ['collection' => 'picks', 'add' => ['a']], '"a" is not one'],
            ['catalog_collections_products', ['collection' => 'picks', 'add' => [999999]], 'no product #999999'],
            ['catalog_collections_products', ['collection' => 'picks', 'set' => [$gone->id]], "no product #{$gone->id}"],
            ['catalog_collections_products', ['collection' => 'picks', 'add' => [$gone->id]], 'in the bin'],
            ['catalog_collections_products', ['collection' => 'picks', 'set' => range(1, 49)], 'no product #'],
            ['catalog_collections_products', ['collection' => 'picks', 'remove' => [$product->id]], "Not on the list: #{$product->id}"],
            ['catalog_collections_products', ['collection' => 'picks', 'move' => 'first'], '`move`'],
            ['catalog_collections_products', ['collection' => 'picks', 'move' => [['product' => $product->id]]], '`move`'],
            ['catalog_collections_products', ['collection' => 'picks', 'move' => [['product' => $product->id, 'position' => 1]]], 'Not on the list'],
            ['catalog_collections_products', ['collection' => 'picks', 'add' => [$product->id], 'move' => [['product' => $product->id, 'position' => 2]]], 'from 1 to 1'],

            // The preview.
            ['catalog_collections_preview', [], 'Name a collection'],
            ['catalog_collections_preview', ['collection' => 'mystery'], 'There are: picks, sale'],
            ['catalog_collections_preview', ['values' => ['source' => 'mixed']], 'called «mixed»'],
            ['catalog_collections_preview', ['values' => ['on_sale' => true]], 'does not read them'],
            ['catalog_collections_preview', ['values' => ['source' => 'filter', 'sort' => 'mystery']], 'no sort «mystery»'],
            ['catalog_collections_preview', ['values' => ['products' => range(1, 49)]], 'at most 48'],
            ['catalog_collections_preview', ['values' => ['products' => 'all']], '`products`'],
            ['catalog_collections_preview', ['values' => ['limit' => 49]], 'limit: The limit is a number from 1 to 48'],
            ['catalog_collections_preview', ['values' => ['limit' => 'many']], 'from 1 to 48'],
            ['catalog_collections_preview', ['values' => ['limit' => [1]]], 'from 1 to 48'],
            ['catalog_collections_preview', ['values' => ['limit' => null]], 'from 1 to 48'],
            ['catalog_collections_preview', ['collection' => 'picks', 'values' => ['limit' => 49]], 'from 1 to 48'],
            ['catalog_collections_preview', ['values' => ['view' => 'list']], 'no view called «list»'],
        ];

        // A facet named with nothing readable in it: dropped, it would open the whole catalogue.
        foreach ([
            [['price' => ['min' => 'abc']], 'is a range'],
            [['price' => ['min' => [1]]], 'is a range'],
            [['price' => ['min' => 5, 'max' => 'abc']], 'is a range'],
            [['price' => ['mni' => 5]], 'is a range'],
            [['price' => ['min' => null]], 'is a range'],
            [['price' => []], 'is a range'],
            [['brand' => []], 'at least one value. catalog_collections_facets'],
        ] as [$set, $says]) {
            $refusals[] = ['catalog_collections_create', ['values' => ['title' => 'A', 'source' => 'filter', 'set' => $set]], $says];
            $refusals[] = ['catalog_collections_update', ['collection' => 'sale', 'values' => ['set' => $set]], $says];
            $refusals[] = ['catalog_collections_preview', ['values' => ['source' => 'filter', 'set' => $set]], $says];
        }

        // A blank switch: the `boolean` rule skips it, and MariaDB then refuses the row with an SQL
        // error while sqlite keeps it and reads "   " as true.
        foreach (['on_sale', 'only_purchasable', 'is_enabled'] as $field) {
            foreach (['', '   '] as $blank) {
                $refusals[] = ['catalog_collections_create', ['values' => ['title' => 'A', 'source' => 'filter', $field => $blank]], "{$field}:"];
                $refusals[] = ['catalog_collections_create', ['values' => ['title' => 'A', 'source' => 'filter', $field => $blank], 'dry_run' => true], "{$field}:"];
                $refusals[] = ['catalog_collections_update', ['collection' => 'sale', 'values' => [$field => $blank]], "{$field}:"];
                $refusals[] = ['catalog_collections_update', ['collection' => 'sale', 'values' => [$field => $blank], 'dry_run' => true], "{$field}:"];

                // `is_enabled` changes nothing in a preview, which leaves it out like the title.
                if ($field !== 'is_enabled') {
                    $refusals[] = ['catalog_collections_preview', ['values' => ['source' => 'filter', $field => $blank]], "{$field}:"];
                }
            }
        }

        foreach ($refusals as [$tool, $arguments, $says]) {
            $this->refused($tool, $arguments, $says);
        }

        $this->assertSame(['picks', 'sale'], ProductCollection::query()->orderBy('id')->pluck('key')->all());
        $this->assertSame(0, DB::table(ProductCollection::PRODUCTS)->count());
        $this->assertTrue($filter->refresh()->on_sale);
    }

    #[Test]
    public function what_is_legal_stays_legal_past_the_refusals(): void
    {
        $this->product('Camera');

        // A set empty as a whole says nothing was narrowed: «New arrivals» is one.
        $empty = $this->content($this->agent('catalog_collections_create', ['values' => ['title' => 'All', 'source' => 'filter', 'set' => []]]))['collection'];
        $this->assertEmpty((array) $empty['filters']);
        $this->assertSame('new', $this->content($this->agent('catalog_collections_create', ['preset' => 'new', 'values' => ['title' => 'Fresh']]))['collection']['preset']);
        $this->assertSame(1, $this->content($this->agent('catalog_collections_preview', ['values' => ['source' => 'filter', 'set' => []]]))['count']);

        // One end of a range is enough, a number may come as a string, and null clears a language.
        $range = $this->content($this->agent('catalog_collections_create', ['values' => ['title' => 'Cheap', 'source' => 'filter', 'set' => ['price' => ['max' => '50']], 'limit' => '4']]))['collection'];
        $this->assertEquals(['price' => ['min' => null, 'max' => 50]], (array) $range['filters']);
        $this->assertSame(4, $range['limit']);
        $this->agent('catalog_collections_update', ['collection' => 'cheap', 'values' => ['link_text' => 'Every one']])->assertOk();
        $cleared = $this->content($this->agent('catalog_collections_update', ['collection' => 'cheap', 'values' => ['link_text' => ['en' => null]]]))['collection'];
        $this->assertArrayNotHasKey('en', (array) $cleared['link_text']);
    }

    #[Test]
    public function a_preview_judges_what_was_sent_and_not_a_limit_saved_under_a_higher_ceiling(): void
    {
        $this->manual('picks', ['limit' => 8]);
        config()->set('webx-catalog-collections.max_limit', 4);

        $this->assertSame(4, $this->content($this->agent('catalog_collections_preview', ['collection' => 'picks', 'values' => ['view' => 'carousel']]))['limit']);
        $this->assertSame(4, $this->content($this->agent('catalog_collections_preview', ['values' => ['source' => 'filter']]))['limit']);
        $this->refused('catalog_collections_preview', ['collection' => 'picks', 'values' => ['limit' => 5]], 'from 1 to 4');
    }

    #[Test]
    public function a_list_past_the_ceiling_is_refused_by_the_form(): void
    {
        config()->set('webx-catalog-collections.max_limit', 1);
        $this->manual('picks', ['limit' => 1]);

        $this->refused('catalog_collections_products', ['collection' => 'picks', 'set' => [$this->product('One')->id, $this->product('Two')->id]], 'at most 1');
    }

    #[Test]
    public function the_resource_says_the_house_rules_and_the_counts(): void
    {
        $this->manual('picks');
        $this->manual('off', ['is_enabled' => false, 'attention' => ProductCollection::VALUE_DELETED]);
        $this->manual('old')->delete();

        $resource = collect($this->app->make(ToolRegistry::class)->resources())->first(static fn (McpResource $one): bool => $one->uri === 'catalog://collections');
        $this->assertInstanceOf(McpResource::class, $resource);

        $read = ($resource->handler)();

        $this->assertSame(['all' => 2, 'enabled' => 1, 'attention' => 1, 'trashed' => 1], $read['counts']);
        $this->assertSame(48, $read['max_limit']);
        $this->assertSame(['new', 'on-sale', 'label'], $read['presets']);
        $this->assertArrayHasKey('not_a_landing', $read['rules']);
        $this->assertArrayHasKey('not_wx_collection', $read['rules']);
        $this->assertSame(['block', 'text', 'template', 'limit'], array_keys($read['rules']['placing']));
        $this->assertStringContainsString('catalog_collections_facets first', $read['rules']['order']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function manual(string $key, array $attributes = []): ProductCollection
    {
        return ProductCollection::query()->create(['key' => $key, 'title' => 'Picks', ...$attributes])->refresh();
    }

    private function label(string $title): Label
    {
        return Label::query()->create(['title' => $title, 'slug' => strtolower($title)])->refresh();
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return list<int>
     */
    private function listed(array $changes): array
    {
        return $this->content($this->agent('catalog_collections_products', ['collection' => 'picks', ...$changes]))['collection']['products'];
    }

    /**
     * Straight at the handler, so what comes out is the exception itself: over the server any
     * other one would read as an error too, and only say «something went wrong» in production.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function refused(string $tool, array $arguments, string $says): void
    {
        $handler = $this->app->make(ToolRegistry::class)->tool($tool)->tool->handler;

        try {
            $handler($arguments);
        } catch (ToolFailure $failure) {
            $this->assertStringContainsString($says, $failure->getMessage(), $tool);

            return;
        }

        $this->fail("{$tool} accepted ".json_encode($arguments));
    }

    /**
     * Everything a write could touch.
     *
     * @return array<string, mixed>
     */
    private function tables(): array
    {
        return [
            'collections' => DB::table('catalog_collections')->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all(),
            'products' => DB::table(ProductCollection::PRODUCTS)->orderBy('collection_id')->orderBy('product_id')->get()->map(static fn (object $row): array => (array) $row)->all(),
            'journal' => DB::table('cms_history')->where('subject_type', ProductCollection::TYPE)->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = [], ?CmsUser $as = null): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($as ?? $this->manager(), 'cms')->tool($bound, $arguments);
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
