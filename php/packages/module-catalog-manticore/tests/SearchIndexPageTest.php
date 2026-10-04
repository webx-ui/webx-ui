<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Manifest\ManifestBuilder;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Engine\Indexer;
use WebxUi\Catalog\Engine\RebuildRunning;
use WebxUi\Catalog\Manticore\Rebuild\RebuildIndex;
use WebxUi\Catalog\Manticore\Rebuild\RebuildProgress;
use WebxUi\Catalog\Tests\TestCase;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * «System → Search index», `catalog_index_status` and the notice over the list (decisions 13,
 * 27–28), with no server at all: that is when somebody opens the page, so the queue and the
 * database still report; the rebuild is a job, one at a time.
 */
final class SearchIndexPageTest extends TestCase
{
    use UsesManticore;

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $this->useManticore($app);
        // A port nobody listens on: refused at once.
        $app['config']->set('webx-catalog-manticore.host', '127.0.0.1');
        $app['config']->set('webx-catalog-manticore.port', 9);
    }

    #[Test]
    public function the_list_says_it_comes_from_the_database(): void
    {
        $product = $this->product('ThinkPad', $this->category('laptops'));
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->getJson($this->api('products'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('fell_back', true);
    }

    #[Test]
    public function the_page_reports_the_queue_when_the_server_does_not_answer(): void
    {
        $product = $this->product('ThinkPad', $this->category('laptops'));
        $this->app->make(Catalog::class)->touch([$product->id]);

        $this->actingAs($this->editor(['search-index.view']), 'cms')->getJson('/api/cms/search-index')
            ->assertOk()
            ->assertJsonPath('data.connection.address', '127.0.0.1:9')
            ->assertJsonPath('data.connection.prefix', $this->prefix)
            ->assertJsonPath('data.connection.available', false)
            ->assertJsonPath('data.products', 1)
            ->assertJsonPath('data.tables', [])
            ->assertJsonPath('data.queue.waiting', 1)
            ->assertJsonPath('data.rebuild.state', RebuildProgress::IDLE);

        $this->actingAs($this->editor(['catalog.view']), 'cms')->getJson('/api/cms/search-index')->assertForbidden();
    }

    #[Test]
    public function the_rebuild_is_a_job_and_one_at_a_time(): void
    {
        Bus::fake();
        $viewer = $this->editor(['search-index.view']);
        $manager = $this->editor(['search-index.view', 'search-index.manage']);

        $this->actingAs($viewer, 'cms')->postJson('/api/cms/search-index/rebuild')->assertForbidden();

        $this->actingAs($manager, 'cms')->postJson('/api/cms/search-index/rebuild')
            ->assertStatus(202)
            ->assertJsonPath('data.state', RebuildProgress::QUEUED);
        Bus::assertDispatched(RebuildIndex::class);

        $this->actingAs($manager, 'cms')->postJson('/api/cms/search-index/rebuild')
            ->assertStatus(409)
            ->assertJsonPath('message', 'A rebuild is already under way.');
        Bus::assertDispatchedTimes(RebuildIndex::class, 1);

        // Unheard of for a quarter of an hour, it is stalled, and may be started again.
        $this->travel(RebuildProgress::STALLED_AFTER + 1)->seconds();
        $this->actingAs($manager, 'cms')->getJson('/api/cms/search-index')->assertJsonPath('data.rebuild.stalled', true);
        $this->actingAs($manager, 'cms')->postJson('/api/cms/search-index/rebuild')->assertStatus(202);
        Bus::assertDispatchedTimes(RebuildIndex::class, 2);
    }

    #[Test]
    public function the_console_and_the_panel_rebuild_under_one_lock(): void
    {
        Bus::fake();
        $manager = $this->editor(['search-index.view', 'search-index.manage']);
        $this->actingAs($manager, 'cms')->getJson('/api/cms/search-index')->assertJsonPath('data.locked', false);

        // A rebuild holds the lock — the console's `--rebuild` or the panel's job, it is one lock.
        $held = Cache::lock(Indexer::LOCK, 60);
        $this->assertTrue($held->get());

        $this->actingAs($manager, 'cms')->getJson('/api/cms/search-index')->assertJsonPath('data.locked', true);
        $this->actingAs($manager, 'cms')->postJson('/api/cms/search-index/rebuild')
            ->assertStatus(409)
            ->assertJsonPath('message', 'Another rebuild is running — it was started from the console. Start this one once it ends.');
        Bus::assertNotDispatched(RebuildIndex::class);

        $this->artisan('webx:catalog:index', ['--rebuild' => true])
            ->expectsOutputToContain('Another rebuild of the catalogue index is running')
            ->assertFailed();

        // A job queued before the console took the lock meets it when the worker runs: it says so
        // and stands down, the tables untouched.
        $progress = $this->app->make(RebuildProgress::class);
        $progress->queue();
        $this->app->call([new RebuildIndex, 'handle']);
        $this->assertSame(RebuildProgress::FAILED, $progress->get()['state']);
        $this->assertStringContainsString('started from the console', (string) $progress->get()['error']);

        $held->release();
        $this->actingAs($manager, 'cms')->getJson('/api/cms/search-index')->assertJsonPath('data.locked', false);
        $this->actingAs($manager, 'cms')->postJson('/api/cms/search-index/rebuild')->assertStatus(202);
    }

    #[Test]
    public function a_failed_rebuild_releases_the_lock(): void
    {
        $this->product('ThinkPad', $this->category('laptops'));

        try {
            $this->app->make(Indexer::class)->rebuild();
            $this->fail('A rebuild with no server must fail.');
        } catch (RebuildRunning $running) {
            throw $running;
        } catch (\Throwable) {
            // The server is not there.
        }

        $this->assertFalse($this->app->make(Indexer::class)->rebuilding());
    }

    #[Test]
    public function a_failed_rebuild_says_why(): void
    {
        $this->product('ThinkPad', $this->category('laptops'));
        $progress = $this->app->make(RebuildProgress::class);
        $progress->queue();

        try {
            $this->app->call([new RebuildIndex, 'handle']);
            $this->fail('A rebuild with no server must fail.');
        } catch (\Throwable) {
            // Thrown on, for the queue to record.
        }

        $this->assertSame(RebuildProgress::FAILED, $progress->get()['state']);
        $this->assertNotNull($progress->get()['error']);
        $this->assertFalse($progress->busy());
    }

    #[Test]
    public function the_section_and_the_tool_are_there_on_manticore(): void
    {
        $manifest = $this->app->make(ManifestBuilder::class)->build();
        $entry = collect($manifest['modules'])->firstWhere('id', 'search-index');

        $this->assertNotNull($entry);
        $this->assertSame('system', $entry['group']);

        $answer = $this->content($this->agent('catalog_index_status'));
        $this->assertFalse($answer['connection']['available']);
        $this->assertSame(0, $answer['queue']['waiting']);
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
