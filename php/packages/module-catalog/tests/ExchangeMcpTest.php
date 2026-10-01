<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Catalog\Exchange\ExchangeProfile;
use WebxUi\Catalog\Models\Product;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * The exchange by an agent's doors (§9 of the exchange spec): a file by address, the panel's
 * suggested mapping when none is given, `dry_run` as the import's own check, an export handed back
 * as a signed link, the run with its first errors, and the rules of the format as a resource.
 */
final class ExchangeMcpTest extends TestCase
{
    private const URL = 'https://supplier.test/price.csv';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    #[Test]
    public function the_tools_are_behind_the_permissions_of_the_api(): void
    {
        $registry = $this->app->make(ToolRegistry::class);
        $permissions = [];

        foreach ($registry->toolsOf('catalog') as $tool) {
            $permissions[$tool->fullName()] = $tool->permissions();
        }

        $any = ['catalog.view', 'catalog.manage', 'catalog.delete'];
        $this->assertSame(['catalog.manage'], $permissions['catalog_import']);

        foreach (['catalog_exchange_columns', 'catalog_export', 'catalog_exchange_run', 'catalog_exchange_profiles'] as $name) {
            $this->assertSame($any, $permissions[$name], $name);
        }

        $this->agent('catalog_import', ['url' => self::URL], $this->editor(['catalog.view']))->assertHasErrors(['[catalog.manage]']);
    }

    #[Test]
    public function an_import_by_address_takes_the_suggested_mapping_and_answers_the_run(): void
    {
        $shoes = $this->category('shoes');
        $this->product('Boot', $shoes, ['sku' => 'B-1']);
        $this->serve("sku,Name,price,category\nB-1,Boot,10,#{$shoes->id}\nN-1,New,abc,#{$shoes->id}\nN-2,Sandal,5,#{$shoes->id}\n");

        $answer = $this->content($this->agent('catalog_import', ['url' => self::URL]));

        $this->assertSame('done', $answer['run']['status']);
        $this->assertSame(['sku' => 'sku', 'Name' => 'name', 'price' => 'price', 'category' => 'category'], $answer['run']['mapping']);
        $this->assertSame([1, 1, 1], [$answer['run']['created'], $answer['run']['updated'], $answer['run']['failed']]);
        $this->assertSame(1, $answer['errors_total']);
        $this->assertSame(3, $answer['errors'][0]['row']);
        $this->assertSame('price', $answer['errors'][0]['column']);
        $this->assertSame('abc', $answer['errors'][0]['value']);
        $this->assertTrue(Product::query()->where('sku', 'N-2')->exists());
    }

    #[Test]
    public function dry_run_is_the_imports_own_check(): void
    {
        $this->serve("sku,name,price\nN-1,New,abc\nN-2,Sandal,5\n");

        $answer = $this->content($this->agent('catalog_import', ['url' => self::URL, 'dry_run' => true]));

        $this->assertTrue($answer['run']['dry_run']);
        $this->assertSame(1, $answer['run']['failed']);
        $this->assertSame(1, $answer['run']['created']);
        $this->assertSame(0, Product::query()->count());
    }

    #[Test]
    public function a_profile_brings_its_mapping_and_options(): void
    {
        $this->product('Boot', null, ['sku' => 'B-1']);
        $profile = ExchangeProfile::query()->create([
            'name' => 'Supplier',
            'direction' => ExchangeProfile::IMPORT,
            'format' => 'csv',
            'options' => ['mode' => 'update'],
            'mapping' => ['Article' => 'sku', 'Rank' => 'priority'],
        ]);
        $this->serve("Article,Rank\nB-1,4\nN-1,9\n");

        $answer = $this->content($this->agent('catalog_import', ['url' => self::URL, 'profile_id' => $profile->id]));

        $this->assertSame([0, 1, 1], [$answer['run']['created'], $answer['run']['updated'], $answer['run']['skipped']]);
        $this->assertSame(4, Product::query()->where('sku', 'B-1')->value('priority'));

        $profiles = $this->content($this->agent('catalog_exchange_profiles', ['direction' => 'import'], $this->editor(['catalog.view'])));
        $this->assertSame('Supplier', $profiles['profiles'][0]['name']);
        $this->assertSame($answer['run']['id'], $profiles['profiles'][0]['last_run_id']);

        $this->agent('catalog_import', ['url' => self::URL, 'profile_id' => 999])->assertHasErrors(['no import profile [999]']);
    }

    #[Test]
    public function an_export_is_handed_back_as_a_signed_link(): void
    {
        $shoes = $this->category('shoes');
        $boot = $this->product('Boot', $shoes, ['sku' => 'B-1']);
        $this->product('Hat', null, ['sku' => 'H-1']);

        $answer = $this->content($this->agent('catalog_export', ['ids' => [$boot->id], 'columns' => ['sku', 'name'], 'format' => 'csv'], $this->editor(['catalog.view'])));

        $this->assertSame('done', $answer['run']['status']);
        $this->assertSame(1, $answer['run']['rows_total']);
        $this->assertStringContainsString('signature=', (string) $answer['run']['file_url']);

        $file = $this->get((string) $answer['run']['file_url']);
        $file->assertOk();
        $this->assertStringContainsString('B-1,Boot', $file->streamedContent());

        $again = $this->content($this->agent('catalog_exchange_run', ['run' => $answer['run']['id']]));
        $this->assertSame($answer['run']['id'], $again['run']['id']);
        $this->assertSame([], $again['errors']);

        $this->agent('catalog_export', ['columns' => ['nope']])->assertHasErrors(['columns']);
    }

    #[Test]
    public function an_export_sent_back_by_its_link_changes_nothing(): void
    {
        $shoes = $this->category('shoes');
        $this->product('Boot', $shoes, ['sku' => 'B-1', 'price' => 12.5]);
        $this->product('Hat', $shoes, ['sku' => 'H-1']);

        $export = $this->content($this->agent('catalog_export', ['format' => 'csv']))['run'];

        // The link ends with the file's name: an import by it knows the format from the address.
        $this->assertStringContainsString('/catalog-export-'.$export['id'].'.csv?', (string) $export['file_url']);
        Http::fake([$export['file_url'] => fn () => Http::response($this->get((string) $export['file_url'])->streamedContent())]);

        $import = $this->content($this->agent('catalog_import', ['url' => $export['file_url']]))['run'];

        $this->assertSame('done', $import['status']);
        $this->assertSame([0, 2, 0], [$import['created'], $import['updated'], $import['failed']]);
        $this->assertSame(12.5, (float) Product::query()->where('sku', 'B-1')->value('price'));
        $this->assertSame(0, HistoryEntry::query()->where('subject_type', Product::TYPE)->where('event', HistoryEntry::UPDATED)->count());
    }

    #[Test]
    public function the_columns_and_the_rules_say_how_a_cell_reads(): void
    {
        $columns = $this->content($this->agent('catalog_exchange_columns', [], $this->editor(['catalog.view'])))['columns'];
        $byKey = array_column($columns, null, 'key');

        $this->assertStringContainsString('#id', $byKey['category']['cell']);
        $this->assertStringContainsString('comma', $byKey['price']['cell']);

        $resource = collect($this->app->make(ToolRegistry::class)->resources())->first(static fn (McpResource $one): bool => $one->uri === 'catalog://exchange');
        $this->assertInstanceOf(McpResource::class, $resource);

        $rules = ($resource->handler)();
        $this->assertContains('csv', $rules['file']['formats']);
        $this->assertContains('xlsx', $rules['file']['formats']);
        $this->assertSame(array_column($columns, 'key'), array_column($rules['columns'], 'key'));
        $this->assertArrayHasKey('create_missing', $rules['options']);
    }

    private function serve(string $csv): void
    {
        // A response per request: the suggestion reads the file once and the run once more.
        Http::fake([self::URL => static fn () => Http::response($csv)]);
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
