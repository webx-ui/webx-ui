<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Passport\AccessToken;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Catalog\Tests\Fixtures\Shop;
use WebxUi\Catalog\Variables\StockStatuses;
use WebxUi\CatalogBrands\BrandsServiceProvider;
use WebxUi\CatalogFeeds\Build\BuildFeed;
use WebxUi\CatalogFeeds\Formats\FeedFormats;
use WebxUi\CatalogFeeds\Http\FeedForm;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Models\FeedBuild;
use WebxUi\CatalogFeeds\Models\Taxonomy;
use WebxUi\CatalogFeeds\Models\TaxonomyItem;
use WebxUi\CatalogFeeds\Taxonomy\ImportTaxonomy;
use WebxUi\CatalogFeeds\Tests\Fixtures\LinesFormat;
use WebxUi\CatalogLabels\LabelsServiceProvider;
use WebxUi\CatalogProperties\CatalogPropertiesServiceProvider;
use WebxUi\CatalogStock\StockServiceProvider;
use WebxUi\Mcp\Calls\Call;
use WebxUi\Mcp\Grants\Grant;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * §12 of the feeds spec: the tools are the catalogue's, an agent walks the path a person walks in
 * the panel, a dry run keeps nothing and starts nothing, and the key goes only to who may write.
 */
final class McpTest extends TestCase
{
    use CountsQueries;

    private const CLIENT = '9d2f6c1e-0a7b-4c3d-8e5f-1a2b3c4d5e6f';

    private const READS = ['list', 'get', 'presets', 'builds', 'build_get', 'exclusions', 'mappings', 'taxonomy_search'];

    private const WRITES = ['create', 'update', 'delete', 'rebuild', 'reissue_key', 'exclusions_set', 'mappings_set', 'taxonomy_import'];

    private Shop $shop;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            BrandsServiceProvider::class,
            LabelsServiceProvider::class,
            StockServiceProvider::class,
            CatalogPropertiesServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::seedOnce($this->app, self::class);
        Storage::fake('local');
        $this->app->make('url')->forceRootUrl('https://example.test');
        $this->app->make('url')->forceScheme('https');
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
            $this->assertSame(['catalog:read', ['catalog.view', 'catalog.manage'], false], $tools["catalog_feeds_{$read}"] ?? null, $read);
        }

        foreach (self::WRITES as $write) {
            $this->assertSame(['catalog:write', ['catalog.manage'], true], $tools["catalog_feeds_{$write}"] ?? null, $write);
        }

        // It keeps nothing, so it has no dry run; it is costly, so it stays with who may write.
        $this->assertSame(['catalog:write', ['catalog.manage'], false], $tools['catalog_feeds_preview'] ?? null);

        $uris = array_map(static fn (McpResource $resource): string => $resource->uri, $registry->resources());
        $this->assertContains('catalog://feed-variables', $uris);
        $this->assertContains('catalog://feed-rules', $uris);
        $this->assertSame([], $registry->toolsOf('catalog-feeds'));
    }

    #[Test]
    public function the_resources_say_what_a_template_may_hold(): void
    {
        $resources = [];

        foreach ($this->app->make(ToolRegistry::class)->resources() as $resource) {
            $resources[$resource->uri] = $resource;
        }

        $variables = ($resources['catalog://feed-variables']->handler)();
        $this->assertContains('sku', array_column($variables['item'], 'name'));
        $this->assertNotContains('sku', array_column($variables['frame'], 'name'));

        $rules = ($resources['catalog://feed-rules']->handler)();
        $this->assertArrayHasKey('escaping', $rules['rules']);
        $this->assertContains('brand', array_column($rules['facets'], 'key'));
    }

    /** The description of `values` sends an agent to the rules for every field, so each has to be there. */
    #[Test]
    public function the_rules_say_what_every_field_of_a_feed_holds(): void
    {
        $said = implode("\n", $this->rules()['rules']);

        foreach ([...FeedForm::FIELDS, 'currency'] as $field) {
            $this->assertMatchesRegularExpression('/`'.$field.'[`.]/', $said, $field);
        }

        foreach ([...Feed::SCHEDULES, 'delimiter', 'description', 'default_availability'] as $value) {
            $this->assertStringContainsString("`{$value}`", $said);
        }
    }

    #[Test]
    public function what_the_rules_say_of_schedules_and_options_is_accepted(): void
    {
        foreach (Feed::SCHEDULES as $schedule) {
            $this->agent('catalog_feeds_create', ['preset' => 'google', 'dry_run' => true, 'values' => ['schedule' => $schedule]])->assertHasNoErrors();
        }

        $feed = $this->content($this->agent('catalog_feeds_create', ['preset' => 'google', 'values' => [
            'schedule' => 'daily',
            'schedule_at' => '06:30',
            'rebuild_on_change' => true,
            'taxonomy' => 'google',
            'options' => ['description' => 'Spare parts', 'default_availability' => 'out_of_stock'],
        ]]))['feed'];

        $this->assertSame('06:30', $feed['schedule_at']);
        $this->assertTrue($feed['rebuild_on_change']);
        $this->assertSame('Spare parts', $feed['options']['description']);
    }

    /** Read the way an agent reads it: from the JSON, with the numbers as keys of an object. */
    #[Test]
    public function the_label_sources_of_the_rules_are_accepted_as_they_are_written(): void
    {
        $rules = json_decode((string) json_encode($this->rules(), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        $example = $rules['label_example'];
        $said = $rules['rules']['labels'];

        $this->assertEqualsCanonicalizing($rules['label_sources'], array_column($example, 'type'));

        foreach ($example as $number => $source) {
            $this->agent('catalog_feeds_create', ['preset' => 'google', 'dry_run' => true, 'values' => ['custom_labels' => [$number => $source]]])
                ->assertHasNoErrors();

            // The words have to name what the example sends, or one of the two is wrong.
            foreach (array_diff(array_keys($source), ['type']) as $key) {
                $this->assertStringContainsString("`{$source['type']}` with `{$key}`", $said);
            }
        }

        $made = $this->content($this->agent('catalog_feeds_create', ['preset' => 'google', 'values' => ['custom_labels' => $example]]))['feed'];
        $preview = $this->content($this->agent('catalog_feeds_preview', ['feed' => $made['id']]));

        $this->assertTrue($preview['ok'], json_encode($preview['errors'], JSON_THROW_ON_ERROR));
        $this->assertStringContainsString('<g:custom_label_3>autumn</g:custom_label_3>', $preview['text']);
        $this->assertStringContainsString('<g:custom_label_1>', $preview['text']);
    }

    #[Test]
    public function an_agent_walks_from_a_preset_to_the_journal_without_keeping_anything_on_a_dry_run(): void
    {
        Queue::fake();
        $editor = $this->editor();
        [$item, $category, $status] = [$this->listItem(), $this->shop->category('Knives'), $this->stockCode()];
        $kept = $this->agent('catalog_feeds_create', ['preset' => 'google', 'values' => ['name' => 'Kept']], $editor);
        $feed = (int) $this->content($kept)['feed']['id'];

        $created = $this->content($this->agent('catalog_feeds_create', ['preset' => 'google', 'dry_run' => true], $editor));
        $this->assertTrue($created['dry_run']);
        $this->assertSame('google', $created['would_be']['feed']['preset']);
        $this->assertNull($created['would_be']['feed']['address'], 'The key of a feed that was not made means nothing.');
        $this->assertSame(1, Feed::query()->count());

        $mapped = $this->content($this->agent('catalog_feeds_mappings_set', [
            'feed' => $feed,
            'stock' => [$status => 'backorder'],
            'categories' => [['category_id' => $category->id, 'taxonomy_item_id' => $item]],
            'dry_run' => true,
        ], $editor));
        $this->assertSame('backorder', $this->stockOf($mapped['would_be'], $status));
        $this->assertSame(0, DB::table('catalog_feed_categories')->count());
        $this->assertSame([], Feed::query()->findOrFail($feed)->stock_map);

        $counted = $this->content($this->agent('catalog_feeds_rebuild', ['feed' => $feed, 'dry_run' => true], $editor));
        $this->assertGreaterThan(0, $counted['would_be']['selected']);
        $this->assertSame(0, $counted['would_be']['excluded']);
        $this->assertNull($counted['would_be']['already_going']);

        Queue::assertNothingPushed();
        $this->assertSame(0, FeedBuild::query()->count());
        $this->assertSame(0, $this->content($this->agent('catalog_feeds_builds', ['feed' => $feed], $editor))['count']);
    }

    #[Test]
    public function an_agent_walks_from_a_preset_to_the_journal(): void
    {
        $editor = $this->editor();
        $status = $this->stockCode();
        $category = $this->shop->category('Knives');
        $this->listItem();

        $presets = $this->content($this->agent('catalog_feeds_presets', [], $editor));
        $this->assertContains('google', array_column($presets['presets'], 'key'));

        $made = $this->content($this->agent('catalog_feeds_create', ['preset' => 'google'], $editor))['feed'];
        $feed = (int) $made['id'];
        $this->assertSame('Google Merchant', $made['name']);
        $this->assertSame('EUR', $made['currency']);
        $this->assertStringContainsString('/feeds/', (string) $made['address']);

        $found = $this->content($this->agent('catalog_feeds_taxonomy_search', ['key' => 'google', 'locale' => 'en', 'q' => 'knives'], $editor));
        $this->assertSame('Home > Knives', $found['items'][0]['path']);

        $mapped = $this->content($this->agent('catalog_feeds_mappings_set', [
            'feed' => $feed,
            'stock' => [$status => 'backorder'],
            'categories' => [['category_id' => $category->id, 'taxonomy_item_id' => $found['items'][0]['id']]],
        ], $editor));
        $this->assertSame('backorder', $this->stockOf($mapped, $status));
        $this->assertNotContains($category->id, $mapped['unmapped']['categories']);
        $this->assertNotContains($status, $mapped['unmapped']['stock']);
        $this->assertContains('preorder', array_column($mapped['warnings'], 'code'));

        // Named statuses change; null goes back to the default.
        $cleared = $this->content($this->agent('catalog_feeds_mappings_set', ['feed' => $feed, 'stock' => [$status => null]], $editor));
        $this->assertContains($status, $cleared['unmapped']['stock']);
        $this->agent('catalog_feeds_mappings_set', ['feed' => $feed, 'stock' => [$status => 'in_stock']], $editor);

        $preview = $this->content($this->agent('catalog_feeds_preview', ['feed' => $feed], $editor));
        $this->assertTrue($preview['ok'], json_encode($preview['errors'], JSON_THROW_ON_ERROR));
        $this->assertStringContainsString('<g:id>', $preview['text']);
        $this->assertGreaterThan(0, $preview['selected']);

        $build = $this->content($this->agent('catalog_feeds_rebuild', ['feed' => $feed], $editor))['build'];
        $this->assertSame(FeedBuild::DONE, $build['status'], (string) $build['error']);
        $this->assertTrue($build['current']);
        $this->assertGreaterThan(0, $build['written']);

        $journal = $this->content($this->agent('catalog_feeds_builds', ['feed' => $feed], $editor));
        $this->assertSame(1, $journal['count']);
        $this->assertSame($build['id'], $journal['builds'][0]['id']);
        $this->assertSame(FeedBuild::MCP, $journal['builds'][0]['trigger']);

        $one = $this->content($this->agent('catalog_feeds_build_get', ['feed' => $feed, 'build' => $build['id']], $editor))['build'];
        $this->assertSame($build['written'], $one['written']);
        $this->assertIsArray($one['rejects']);

        $this->get((string) $made['address'])->assertOk();
        $this->assertSame(1, $this->content($this->agent('catalog_feeds_list', [], $editor))['count']);
    }

    #[Test]
    public function the_list_reads_no_build_per_feed_whether_the_class_is_there_or_gone(): void
    {
        $this->app->make(FeedFormats::class)->register(new LinesFormat);

        // Two of a kind: one feed alone would read its build once and look like no loop at all.
        foreach (range(1, 4) as $number) {
            $feed = $this->feed(['name' => 'Feed '.$number, 'format' => 'text', 'driver' => 'lines']);
            $this->built($feed);

            if ($number > 2) {
                $feed->forceFill(['driver' => 'gone'])->save();
            }
        }

        $editor = $this->editor();
        $this->agent('catalog_feeds_list', [], $editor)->assertOk();

        $answer = $this->assertNoRepeatedQueries(fn (): TestResponse => $this->agent('catalog_feeds_list', [], $editor)->assertOk());

        $feeds = $this->content($answer)['feeds'];

        $this->assertCount(4, $feeds);

        foreach ($feeds as $feed) {
            $this->assertStringEndsWith('.lines', (string) $feed['address']);
        }
    }

    #[Test]
    public function a_refusal_says_what_a_panel_would_say(): void
    {
        $this->agent('catalog_feeds_create', ['values' => ['name' => 'Odd', 'format' => 'xml', 'item' => '<item>{{nam}}</item>']])
            ->assertHasErrors(['{{nam}}', 'Similar: name']);

        $this->agent('catalog_feeds_create', ['values' => ['name' => 'Odd', 'format' => 'xml', 'item' => '{{#images}}{{ulr}}{{/images}}']])
            ->assertHasErrors(['url']);

        $this->agent('catalog_feeds_create', ['values' => ['name' => 'Odd', 'format' => 'xml', 'item' => '<item>{{name}}']])
            ->assertHasErrors(['line']);

        $this->agent('catalog_feeds_create', ['preset' => 'google', 'values' => ['currency' => 'USD']])->assertHasErrors(['currency', 'EUR']);
        $this->agent('catalog_feeds_create', ['preset' => 'mystery'])->assertHasErrors(['preset']);
        $this->agent('catalog_feeds_create', ['preset' => 'google', 'values' => ['access_key' => 'mine']])->assertHasErrors(['access_key']);
        $this->agent('catalog_feeds_get', ['feed' => 404])->assertHasErrors(['no feed']);

        $feed = $this->feed();
        $this->agent('catalog_feeds_update', ['feed' => $feed->id, 'values' => []])->assertHasErrors(['values']);
        $this->agent('catalog_feeds_mappings_set', ['feed' => $feed->id])->assertHasErrors(['stock']);
        $this->agent('catalog_feeds_mappings_set', ['feed' => $feed->id, 'stock' => ['mystery' => 'in_stock']])->assertHasErrors(['mystery']);
        $this->agent('catalog_feeds_preview', [])->assertHasErrors(['feed']);
        $this->agent('catalog_feeds_preview', ['feed' => $feed->id, 'product_ids' => range(1, 21)])->assertHasErrors(['product_ids']);

        $this->assertSame(1, Feed::query()->count());
    }

    #[Test]
    public function a_mistake_in_a_preview_is_the_answer_and_nothing_is_kept(): void
    {
        $feed = $this->feed();

        $wrong = $this->content($this->agent('catalog_feeds_preview', ['feed' => $feed->id, 'values' => ['item' => '<item>{{name}}']]));
        $this->assertFalse($wrong['ok']);
        // An unclosed tag shows where the text ends.
        $this->assertSame('foot', $wrong['errors'][0]['part']);
        $this->assertNotNull($wrong['errors'][0]['line']);
        $this->assertSame('<item>{{name}}</item>', $feed->refresh()->item);

        // Without a feed there is nothing to name, and a preset is enough.
        $this->assertTrue($this->content($this->agent('catalog_feeds_preview', ['values' => ['preset' => 'google']]))['ok']);
        $this->assertSame(1, Feed::query()->count());
    }

    #[Test]
    public function an_update_changes_only_what_is_sent_and_a_dry_run_keeps_it_as_it_was(): void
    {
        $feed = $this->feed();

        $dry = $this->content($this->agent('catalog_feeds_update', ['feed' => $feed->id, 'values' => ['name' => 'Renamed'], 'dry_run' => true]));
        $this->assertSame('Renamed', $dry['would_be']['feed']['name']);
        $this->assertSame('Spare parts', $feed->refresh()->name);

        $this->agent('catalog_feeds_update', ['feed' => $feed->id, 'values' => ['name' => 'Renamed']]);
        $this->assertSame('Renamed', $feed->refresh()->name);
        $this->assertSame('<item>{{name}}</item>', $feed->item);
        $this->assertSame(HistoryEntry::query()->where('subject_id', $feed->id)->latest('id')->value('source'), 'mcp');
    }

    #[Test]
    public function a_dry_run_of_a_delete_leaves_the_files_and_the_real_one_takes_them(): void
    {
        $feed = $this->feed();
        $build = $this->built($feed);
        $path = (string) $build->path;
        Storage::disk('local')->assertExists($path);

        $dry = $this->content($this->agent('catalog_feeds_delete', ['feed' => $feed->id, 'dry_run' => true]));
        $this->assertSame(1, $dry['would_delete']['builds']);
        $this->assertSame($feed->address(), $dry['would_delete']['address']);
        $this->assertNotNull(Feed::query()->find($feed->id));
        Storage::disk('local')->assertExists($path);

        $this->assertSame($feed->id, $this->content($this->agent('catalog_feeds_delete', ['feed' => $feed->id]))['deleted']);
        $this->assertNull(Feed::query()->find($feed->id));
        Storage::disk('local')->assertMissing($path);
        $this->get($feed->address())->assertNotFound();
    }

    #[Test]
    public function a_reissue_closes_the_old_address_and_leaves_the_key_out_of_the_journals(): void
    {
        $feed = $this->feed();
        $this->built($feed);
        $old = $feed->address();

        $dry = $this->content($this->agent('catalog_feeds_reissue_key', ['feed' => $feed->id, 'dry_run' => true]));
        $this->assertSame($old, $dry['stops_working']);
        $this->assertSame($old, $feed->refresh()->address());
        $this->get($old)->assertOk();

        $answer = $this->content($this->agent('catalog_feeds_reissue_key', ['feed' => $feed->id]));
        $this->assertNotSame($old, $answer['address']);
        $this->assertSame($feed->refresh()->address(), $answer['address']);
        $this->get($old)->assertNotFound();
        $this->get($answer['address'])->assertOk();

        $row = HistoryEntry::query()->where('event', HistoryEntry::KEY_REISSUED)->sole();
        $this->assertSame('mcp', $row->source);
        $this->assertStringNotContainsString($feed->access_key, (string) json_encode($row->toArray()));
        $this->assertStringNotContainsString($feed->access_key, (string) json_encode(Call::query()->get()->toArray()));
        $this->assertCount(1, $this->content($this->agent('catalog_feeds_builds', ['feed' => $feed->id]))['reissues']);
    }

    #[Test]
    public function the_address_goes_only_to_who_may_write(): void
    {
        $feed = $this->feed();
        $writer = $this->editor();

        $this->assertSame($feed->address(), $this->content($this->agent('catalog_feeds_get', ['feed' => $feed->id], $writer))['feed']['address']);

        $viewer = $this->editor(['catalog.view']);
        $this->assertNull($this->content($this->agent('catalog_feeds_get', ['feed' => $feed->id], $viewer))['feed']['address']);
        $this->assertNull($this->content($this->agent('catalog_feeds_list', [], $viewer))['feeds'][0]['address']);

        // Holding `catalog.manage` is not enough: the person let this agent in only to look.
        $key = $this->editor();
        $key->withAccessToken(new AccessToken(['oauth_user_id' => $key->id, 'oauth_scopes' => ['catalog:read']]));
        $this->assertNull($this->content($this->agent('catalog_feeds_get', ['feed' => $feed->id], $key))['feed']['address']);

        $connected = $this->editor();
        $connected->withAccessToken(new AccessToken(['oauth_user_id' => $connected->id, 'oauth_scopes' => ['mcp:use'], 'oauth_client_id' => self::CLIENT]));
        $this->grant($connected, ['read_only' => true]);
        $this->assertNull($this->content($this->agent('catalog_feeds_list', [], $connected))['feeds'][0]['address']);

        $trusted = $this->editor();
        $trusted->withAccessToken(new AccessToken(['oauth_user_id' => $trusted->id, 'oauth_scopes' => ['mcp:use'], 'oauth_client_id' => self::CLIENT]));
        $this->grant($trusted, ['read_only' => false]);
        $this->assertSame($feed->address(), $this->content($this->agent('catalog_feeds_list', [], $trusted))['feeds'][0]['address']);
    }

    #[Test]
    public function a_token_without_the_scope_is_told_which_one_it_lacks(): void
    {
        $feed = $this->feed();
        $reader = $this->editor();
        $reader->withAccessToken(new AccessToken(['oauth_user_id' => $reader->id, 'oauth_scopes' => ['catalog:read']]));

        $this->agent('catalog_feeds_list', [], $reader)->assertOk();
        $this->agent('catalog_feeds_update', ['feed' => $feed->id, 'values' => ['name' => 'Renamed']], $reader)
            ->assertHasErrors(['[catalog:write]', 'catalog_feeds_update']);
        $this->agent('catalog_feeds_preview', ['feed' => $feed->id], $reader)->assertHasErrors(['[catalog:write]']);

        $this->agent('catalog_feeds_update', ['feed' => $feed->id, 'values' => ['name' => 'Renamed']], $this->editor(['catalog.view']))
            ->assertHasErrors(['[catalog.manage]']);

        $this->assertSame('Spare parts', $feed->refresh()->name);
    }

    #[Test]
    public function exclusions_say_what_changes_and_a_dry_run_changes_nothing(): void
    {
        $feed = $this->feed();
        $first = (int) $this->shop->productOf('Knives')->id;
        $second = (int) $this->shop->productOf('Knives', 1)->id;

        $dry = $this->content($this->agent('catalog_feeds_exclusions_set', ['feed' => $feed->id, 'add' => [$first], 'dry_run' => true]));
        $this->assertSame([$first], $dry['would_be']['added']);
        $this->assertSame(0, $this->content($this->agent('catalog_feeds_exclusions', ['feed' => $feed->id]))['count']);

        $this->agent('catalog_feeds_exclusions_set', ['feed' => $feed->id, 'add' => [$first, $second]]);
        $listed = $this->content($this->agent('catalog_feeds_exclusions', ['feed' => $feed->id]));
        $this->assertSame([$first, $second], array_column($listed['products'], 'id'));

        $answer = $this->content($this->agent('catalog_feeds_exclusions_set', ['feed' => $feed->id, 'add' => [$first], 'remove' => [$second]]));
        $this->assertSame([], $answer['added'], 'It was out already.');
        $this->assertSame([$second], $answer['removed']);
        $this->assertSame(1, $answer['excluded']);

        $this->agent('catalog_feeds_exclusions_set', ['feed' => $feed->id])->assertHasErrors(['add']);
        $this->agent('catalog_feeds_exclusions_set', ['feed' => $feed->id, 'add' => [$first], 'remove' => [$first]])->assertHasErrors(['both']);
        $this->agent('catalog_feeds_exclusions_set', ['feed' => $feed->id, 'add' => [999999]])->assertHasErrors(['add.0']);
    }

    #[Test]
    public function an_import_goes_by_https_and_a_dry_run_queues_nothing(): void
    {
        Bus::fake();

        $dry = $this->content($this->agent('catalog_feeds_taxonomy_import', ['key' => 'google', 'locale' => 'en-US', 'dry_run' => true]));
        $this->assertStringStartsWith('https://', $dry['would_fetch']);
        $this->agent('catalog_feeds_taxonomy_import', ['key' => 'google', 'url' => 'https://example.test/list.txt', 'dry_run' => true]);
        Bus::assertNotDispatched(ImportTaxonomy::class);
        $this->assertSame(0, Taxonomy::query()->count());

        $this->agent('catalog_feeds_taxonomy_import', ['key' => 'google', 'url' => 'http://example.test/list.txt'])->assertHasErrors(['url']);
        $this->agent('catalog_feeds_taxonomy_import', ['key' => 'mystery'])->assertHasErrors(['mystery']);
        Bus::assertNotDispatched(ImportTaxonomy::class);

        $answer = $this->content($this->agent('catalog_feeds_taxonomy_import', ['key' => 'google', 'locale' => 'en-US']));
        $this->assertSame('queued', $answer['taxonomy']['status']);
        Bus::assertDispatchedTimes(ImportTaxonomy::class, 1);

        $this->assertSame('queued', $this->content($this->agent('catalog_feeds_taxonomy_search', ['key' => 'google', 'locale' => 'en-US']))['taxonomy']['status']);
    }

    #[Test]
    public function a_feed_switched_off_is_not_built_and_a_dry_run_says_so(): void
    {
        Queue::fake();
        $feed = $this->feed(['is_active' => false]);

        $this->agent('catalog_feeds_rebuild', ['feed' => $feed->id, 'dry_run' => true])->assertHasErrors(['off']);
        $this->agent('catalog_feeds_rebuild', ['feed' => $feed->id])->assertHasErrors(['off']);

        Queue::assertNotPushed(BuildFeed::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        foreach ($this->app->make(ToolRegistry::class)->resources() as $resource) {
            if ($resource->uri === 'catalog://feed-rules') {
                return ($resource->handler)();
            }
        }

        $this->fail('No catalog://feed-rules.');
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

    /**
     * @param  array<string, mixed>  $settings
     */
    private function feed(array $settings = []): Feed
    {
        $feed = new Feed;
        $feed->forceFill([
            'name' => 'Spare parts',
            'format' => 'xml',
            'locale' => 'en',
            'currency' => 'EUR',
            'head' => '<items>',
            'item' => '<item>{{name}}</item>',
            'foot' => '</items>',
            ...$settings,
        ])->save();

        return $feed->refresh();
    }

    private function built(Feed $feed): FeedBuild
    {
        $this->agent('catalog_feeds_rebuild', ['feed' => $feed->id]);
        $build = FeedBuild::query()->where('feed_id', $feed->id)->sole();
        $this->assertSame(FeedBuild::DONE, $build->status, (string) $build->error);

        return $build;
    }

    private function stockCode(): string
    {
        return $this->app->make(StockStatuses::class)->all('en')[0]['code'];
    }

    /**
     * @param  array<string, mixed>  $mappings
     */
    private function stockOf(array $mappings, string $code): ?string
    {
        return array_column($mappings['stock'], 'value', 'code')[$code] ?? null;
    }

    private function listItem(): int
    {
        $list = Taxonomy::query()->forceCreate(['key' => 'google', 'locale' => 'en-US', 'imported_at' => Carbon::now()]);

        return (int) TaxonomyItem::query()->forceCreate(['taxonomy_id' => $list->id, 'external_id' => '632', 'path' => 'Home > Knives'])->id;
    }

    /**
     * @param  array<string, mixed>  $terms
     */
    private function grant(CmsUser $user, array $terms): Grant
    {
        return Grant::query()->forceCreate([
            'cms_user_id' => $user->id,
            'oauth_client_id' => self::CLIENT,
            'client_name' => 'Claude',
            'redirect_host' => 'claude.ai',
            'consent_version' => '2026-09-21',
            'read_only' => false,
            'created_at' => Carbon::now(),
            ...$terms,
        ]);
    }
}
