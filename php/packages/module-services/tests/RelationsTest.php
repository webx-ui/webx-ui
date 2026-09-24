<?php

declare(strict_types=1);

namespace WebxUi\Services\Tests;

use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Collections\CollectionSources;
use WebxUi\Admin\Relations\Relations;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Blocks\Models\Block;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Services\Models\Service;
use WebxUi\Services\Tests\Fixtures\PairingSource;

/**
 * Services as the other end of a relation (§3 of the recipes spec): the target this module
 * registers, the rows it takes along when a service is deleted for good, and a block that shows
 * records related to services — written through both doors of a service, and read on its page.
 */
final class RelationsTest extends TestCase
{
    private PairingSource $source;

    protected function setUp(): void
    {
        parent::setUp();

        $this->source = new PairingSource;
        $this->app->make(CollectionSources::class)->register($this->source);

        $block = Block::query()->create(['slug' => 'pairings', 'title' => 'Pairings']);
        $block->saveVersion([
            'template' => '<ul data-wx-block="pairings">@foreach ($list[\'items\'] as $one)<li>{{ $one[\'text\'] }}</li>@endforeach</ul>',
            'schema' => [['id' => 'list', 'type' => 'wx-collection', 'props' => ['source' => 'pairings']]],
        ]);
        $block->publish();
    }

    #[Test]
    public function the_picker_finds_services_with_their_categories_behind_the_services_permission(): void
    {
        $massage = $this->service('massage');
        $this->service('yoga');
        $wellness = $this->category('wellness');
        $massage->syncCategories([$wellness->id]);

        $this->actingAs($this->editor(['services.view']), 'cms')
            ->getJson('/api/cms/relations/service?q=mass')
            ->assertOk()
            ->assertExactJson(['data' => [[
                'id' => $massage->id,
                'title' => 'Massage',
                'subtitle' => 'Wellness',
                'thumb' => null,
                'visible' => true,
                'trashed' => false,
            ]]]);

        $this->actingAs($this->editor(['pages.view']), 'cms')
            ->getJson('/api/cms/relations/service')
            ->assertForbidden();
    }

    #[Test]
    public function a_service_deleted_for_good_takes_the_rows_pointing_at_it_and_the_bin_does_not(): void
    {
        $massage = $this->service('massage');

        DB::table(Relations::TABLE)->insert([
            'owner_type' => 'recipe', 'owner_id' => 4, 'role' => 'services',
            'target_type' => 'service', 'target_id' => $massage->id, 'position' => 0,
        ]);

        $massage->delete();
        $this->assertSame(1, DB::table(Relations::TABLE)->count());

        $massage->forceDelete();
        $this->assertSame(0, DB::table(Relations::TABLE)->count());
    }

    #[Test]
    public function the_editors_save_keeps_the_relation_filter_cleaned(): void
    {
        $massage = $this->service('massage');
        $yoga = $this->service('yoga');

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($yoga->id), [
                'values' => ['blocks' => [[
                    'key' => 'k1',
                    'type' => 'pairings',
                    'values' => ['list' => ['related' => ['type' => 'service', 'ids' => [(string) $yoga->id, $massage->id, $yoga->id]]]],
                ]]],
            ])
            ->assertOk();

        $this->assertSame(
            ['categories' => [], 'limit' => null, 'filter' => false, 'markup' => null, 'related' => ['type' => 'service', 'ids' => [$massage->id, $yoga->id]]],
            $yoga->refresh()->draftValues()['blocks'][0]['values']['list'],
        );
    }

    #[Test]
    public function an_agents_edit_keeps_the_relation_filter_cleaned(): void
    {
        $yoga = $this->service('yoga');
        $yoga->blocks = [['key' => 'k-pairings', 'type' => 'pairings', 'values' => []]];
        $yoga->save();

        $this->agent('blocks_edit_content', [
            'entity' => 'service',
            'id' => $yoga->id,
            'ops' => [['op' => 'set', 'key' => 'k-pairings', 'values' => [
                'list' => ['related' => ['type' => 'service', 'ids' => [99], 'current' => true]],
            ]]],
        ], $this->editor(['services.view', 'services.manage', 'blocks.manage']))->assertOk();

        $this->assertSame(
            ['categories' => [], 'limit' => null, 'filter' => false, 'markup' => null, 'related' => ['type' => 'service', 'ids' => [], 'current' => true]],
            $yoga->refresh()->draftValues()['blocks'][0]['values']['list'],
        );
    }

    #[Test]
    public function on_the_page_of_a_service_the_block_shows_what_is_related_to_that_service(): void
    {
        $yoga = $this->service('yoga');
        $yoga->blocks = [[
            'key' => 'k1',
            'type' => 'pairings',
            'values' => ['list' => ['related' => ['type' => 'service', 'ids' => [], 'current' => true]]],
        ]];
        $yoga->save();

        $this->assertStringContainsString('<li>Tea</li>', (string) $yoga->renderBlocks());
        $this->assertSame(['type' => 'service', 'ids' => [$yoga->id]], $this->source->asked?->related());
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments, CmsUser $as): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($as, 'cms')->tool($bound, $arguments);
    }
}
