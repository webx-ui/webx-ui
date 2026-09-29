<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Catalog\Parts\ProductParts;
use WebxUi\Catalog\Tests\Fixtures\NotePart;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * The catalogue by an agent's doors (§12): the permissions of §12.1 — delete named
 * `catalog.delete` rather than the default `catalog.manage` — the form's own checks, `dry_run`
 * that takes the write back, and the parts of the form as a resource.
 */
final class McpTest extends TestCase
{
    #[Test]
    public function every_tool_is_behind_the_permission_of_the_table(): void
    {
        $registry = $this->app->make(ToolRegistry::class);
        $permissions = [];

        foreach ($registry->toolsOf('catalog') as $tool) {
            $permissions[$tool->fullName()] = $tool->permissions();
        }

        $this->assertSame(['catalog.view', 'catalog.manage'], $permissions['catalog_products_list']);
        $this->assertSame(['catalog.view', 'catalog.manage'], $permissions['catalog_categories_tree']);
        $this->assertSame(['catalog.manage'], $permissions['catalog_products_update']);
        $this->assertSame(['catalog.manage'], $permissions['catalog_categories_move']);
        $this->assertSame(['catalog.manage'], $permissions['catalog_bulk']);
        $this->assertSame(['catalog.delete'], $permissions['catalog_products_delete']);
        $this->assertSame(['catalog.delete'], $permissions['catalog_products_restore']);
        $this->assertSame(['catalog.delete'], $permissions['catalog_categories_delete']);
        $this->assertSame(['catalog.delete'], $permissions['catalog_categories_restore']);

        $uris = array_map(static fn (McpResource $resource): string => $resource->uri, $registry->resources());

        foreach (['facets', 'fields', 'addresses', 'categories', 'product-parts', 'bulk-actions'] as $name) {
            $this->assertContains('catalog://'.$name, $uris);
        }
    }

    #[Test]
    public function an_editors_agent_cannot_delete_what_the_editor_cannot(): void
    {
        $product = $this->product('Boot');
        $manager = $this->editor(['catalog.view', 'catalog.manage']);

        $this->agent('catalog_products_delete', ['product' => $product->id], $manager)->assertHasErrors(['[catalog.delete]']);
        $this->assertNotSoftDeleted($product);

        // Nor through the bulk tool, which the manager may use for everything else.
        $this->agent('catalog_bulk', ['action' => 'delete', 'selection' => ['ids' => [$product->id]]], $manager)
            ->assertHasErrors(['catalog.delete']);
        $this->assertNotSoftDeleted($product);

        $this->agent('catalog_products_delete', ['product' => $product->id], $this->editor(['catalog.view', 'catalog.delete']))->assertOk();
        $this->assertSoftDeleted($product);
    }

    #[Test]
    public function a_reader_reads_and_is_refused_a_write(): void
    {
        $product = $this->product('Boot', $this->category('shoes'));
        $reader = $this->editor(['catalog.view']);

        $list = $this->content($this->agent('catalog_products_list', ['q' => 'Boot'], $reader));
        $this->assertSame(1, $list['total']);
        $this->assertSame($product->id, $list['products'][0]['id']);

        $this->agent('catalog_products_unpublish', ['product' => $product->id], $reader)->assertHasErrors(['[catalog.manage]']);
    }

    #[Test]
    public function an_update_goes_through_the_form_and_into_the_journal_as_mcp(): void
    {
        $product = $this->product('Boot', $this->category('shoes'));

        $this->agent('catalog_products_update', ['product' => $product->id, 'values' => ['priority' => 7]])->assertOk();

        $this->assertSame(7, $product->refresh()->priority);
        $this->assertSame('mcp', HistoryEntry::query()->where('subject_id', $product->id)->where('event', HistoryEntry::UPDATED)->value('source'));

        $this->agent('catalog_products_update', ['product' => $product->id, 'values' => ['stock.status' => 'out']])
            ->assertHasErrors(['no part [stock]', 'Known parts: none']);
    }

    #[Test]
    public function dry_run_does_the_write_and_takes_it_back(): void
    {
        $orphan = $this->product('Orphan');

        $this->agent('catalog_products_publish', ['product' => $orphan->id, 'dry_run' => true])->assertHasErrors(['main category']);

        $answer = $this->content($this->agent('catalog_products_update', ['product' => $orphan->id, 'values' => ['priority' => 3], 'dry_run' => true]));
        $this->assertTrue($answer['dry_run']);
        $this->assertSame(3, $answer['would_be']['values']['priority']);
        $this->assertSame(0, $orphan->refresh()->priority);

        $bulk = $this->content($this->agent('catalog_bulk', ['action' => 'unpublish', 'selection' => ['query' => []], 'dry_run' => true]));
        $this->assertSame(1, $bulk['would_touch']);
        $this->assertSame('Orphan', $bulk['first'][0]['name']);
    }

    #[Test]
    public function the_parts_of_the_form_are_read_from_the_registry(): void
    {
        $this->app->make(ProductParts::class)->register(new NotePart);

        $resource = collect($this->app->make(ToolRegistry::class)->resources())->first(static fn (McpResource $one): bool => $one->uri === 'catalog://product-parts');
        $this->assertInstanceOf(McpResource::class, $resource);

        $parts = ($resource->handler)()['parts'];
        $this->assertSame('notes', $parts[0]['key']);
        $this->assertNotEmpty($parts[0]['fields']);
        $this->assertArrayHasKey('module', $parts[0]);
    }

    #[Test]
    public function the_switched_off_fields_are_said_in_the_resource(): void
    {
        config(['webx-catalog.fields.barcode' => false]);

        $resource = collect($this->app->make(ToolRegistry::class)->resources())->first(static fn (McpResource $one): bool => $one->uri === 'catalog://fields');
        $fields = ($resource->handler)();

        $this->assertFalse($fields['barcode']);
        $this->assertTrue($fields['price']);
        $this->assertContains('pcs', array_column($fields['units'], 'value'));
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
