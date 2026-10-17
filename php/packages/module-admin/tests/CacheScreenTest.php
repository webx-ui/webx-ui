<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Cache\CacheModule;
use WebxUi\Admin\Cache\CacheReset;
use WebxUi\Admin\Cache\McpCacheModule;
use WebxUi\Admin\Console\DoctorCommand;
use WebxUi\Admin\Doctor\Checks\CacheStore;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\History\HistoryContext;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Tests\Fixtures\Editor;
use WebxUi\Cache\CacheGroups;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Tool;

/**
 * «System → Cache» and the three doors of the reset (WEBX_UI_CACHE.md §5): the page behind
 * `cache.view`, the button behind `cache.reset`, the command, the agent's tool with its
 * `dry_run` — and the row every reset leaves in the journal.
 */
final class CacheScreenTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(CacheGroups::class)->declare('menu', 'Menu');
    }

    #[Test]
    public function the_section_stands_under_system_with_two_permissions(): void
    {
        $module = $this->app->make(ModuleRegistry::class)->get(CacheModule::ID);

        $this->assertInstanceOf(McpCacheModule::class, $module, 'the mcp package is beside the frame here: the module with the tool is the one registered');

        $this->actingAs(new Editor([CacheModule::VIEW]))->getJson('/api/cms/manifest')
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'cache',
                'group' => 'system',
                'section' => 'search',
                'permissions' => ['cache.view', 'cache.reset'],
            ]);
    }

    #[Test]
    public function the_page_shows_the_store_and_every_group_with_its_version(): void
    {
        $this->actingAs(new Editor(['other.view']))->getJson('/api/cms/cache')->assertForbidden();

        $version = $this->app->make(CacheGroups::class)->version('menu');

        $this->actingAs(new Editor([CacheModule::VIEW]))->getJson('/api/cms/cache')
            ->assertOk()
            ->assertJsonPath('data.driver', 'array')
            ->assertJsonPath('data.store', 'array')
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.ttl', 86400)
            ->assertJsonPath('data.groups.0.id', 'locales')
            ->assertJsonPath('data.groups.0.title', 'Languages')
            ->assertJsonPath('data.groups.0.models', ['Locale'])
            ->assertJsonPath('data.groups.4.id', 'menu')
            ->assertJsonPath('data.groups.4.title', 'Menu')
            ->assertJsonPath('data.groups.4.version', $version)
            ->assertJsonPath('data.groups.4.models', []);
    }

    #[Test]
    public function the_button_moves_the_versions_and_signs_the_journal(): void
    {
        $groups = $this->app->make(CacheGroups::class);
        $before = $groups->versions('menu', 'locales');

        $this->actingAs(new Editor([CacheModule::VIEW]))->postJson('/api/cms/cache/reset')->assertForbidden();
        $this->assertSame($before, $groups->versions('menu', 'locales'));

        $this->actingAs(new Editor([CacheModule::RESET], 7, 'Anna'))->postJson('/api/cms/cache/reset')
            ->assertOk()
            ->assertJsonPath('data.reset', ['locales', 'routes', 'screens', 'theme', 'menu'])
            ->assertJsonPath('data.groups.4.version', $before['menu'] + 1);

        $this->assertSame($before['locales'] + 1, $groups->version('locales'));

        $row = HistoryEntry::query()->sole();
        $this->assertSame(CacheReset::TYPE, $row->subject_type);
        $this->assertSame(CacheReset::SUBJECT, $row->subject_id);
        $this->assertSame(HistoryEntry::UPDATED, $row->event);
        $this->assertSame(HistoryContext::PANEL, $row->source);
        $this->assertSame('Anna', $row->admin_name);
        $this->assertSame([['field' => 'groups', 'from' => null, 'to' => CacheReset::ALL]], $row->changes);

        // One group: only it moves, and the row names it.
        $this->actingAs(new Editor([CacheModule::RESET], 7, 'Anna'))->postJson('/api/cms/cache/reset', ['group' => 'menu'])
            ->assertOk()
            ->assertJsonPath('data.reset', ['menu'])
            ->assertJsonPath('data.groups.4.version', $before['menu'] + 2);
        $this->assertSame($before['locales'] + 1, $groups->version('locales'));
        $this->assertSame('menu', HistoryEntry::query()->latest('id')->firstOrFail()->changes[0]['to']);

        $this->actingAs(new Editor([CacheModule::RESET]))->postJson('/api/cms/cache/reset', ['group' => 'menus'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['group']);

        // The page reads the journal back as any record's history: behind the section's permission.
        $this->actingAs(new Editor([CacheModule::VIEW]))->getJson('/api/cms/history/cache/0')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('data.0.changes.0.label', 'Groups')
            ->assertJsonPath('data.0.admin.name', 'Anna');
        $this->actingAs(new Editor(['other.view']))->getJson('/api/cms/history/cache/0')->assertForbidden();
    }

    #[Test]
    public function the_command_resets_from_the_console(): void
    {
        $groups = $this->app->make(CacheGroups::class);
        $before = $groups->versions('menu', 'theme');

        $this->artisan('webx:cache:reset', ['--group' => ['menu']])
            ->expectsOutputToContain('menu')
            ->expectsOutputToContain('Reset 1 cache group on the array store.')
            ->assertSuccessful();

        $this->assertSame($before['menu'] + 1, $groups->version('menu'));
        $this->assertSame($before['theme'], $groups->version('theme'));
        $this->assertSame(HistoryContext::CONSOLE, HistoryEntry::query()->sole()->source);

        $this->artisan('webx:cache:reset')
            ->expectsOutputToContain('Reset 5 cache groups on the array store.')
            ->assertSuccessful();
        $this->assertSame($before['theme'] + 1, $groups->version('theme'));

        $this->artisan('webx:cache:reset', ['--group' => ['menus']])
            ->expectsOutputToContain('There is no cache group [menus]')
            ->assertFailed();
    }

    #[Test]
    public function the_agent_has_the_same_button_and_a_rehearsal_of_it(): void
    {
        $groups = $this->app->make(CacheGroups::class);
        $module = $this->app->make(ModuleRegistry::class)->get(CacheModule::ID);
        $this->assertInstanceOf(McpCacheModule::class, $module);

        [$tool] = $module->mcpTools();
        $this->assertSame('reset', $tool->name);
        $this->assertTrue($tool->mutating);
        $this->assertSame([CacheModule::RESET], $tool->permissions);
        $this->assertArrayHasKey(Tool::DRY_RUN, $tool->inputSchema['properties']);

        $before = $groups->version('menu');

        $this->assertSame(
            ['dry_run' => true, 'would_reset' => ['menu']],
            ($tool->handler)(['group' => 'menu', 'dry_run' => true]),
        );
        $this->assertSame($before, $groups->version('menu'), 'a rehearsal moves nothing');
        $this->assertSame(0, HistoryEntry::query()->count());

        $this->assertSame(
            ['reset' => ['menu'], 'store' => 'array'],
            ($tool->handler)(['group' => 'menu']),
        );
        $this->assertSame($before + 1, $groups->version('menu'));
        $this->assertSame(1, HistoryEntry::query()->count());

        $this->assertSame(['locales', 'routes', 'screens', 'theme', 'menu'], ($tool->handler)([])['reset']);

        $this->expectException(ToolFailure::class);
        $this->expectExceptionMessage('There is no cache group [menus]');
        ($tool->handler)(['group' => 'menus']);
    }

    #[Test]
    public function the_doctor_says_what_the_store_means(): void
    {
        $check = $this->app->make(CacheStore::class);

        $found = $check->run();
        $this->assertSame(Diagnosis::OK, $found[0]->state, 'the array store is fine in testing');
        $this->assertStringContainsString('array (array)', $found[0]->detail);

        $this->app->detectEnvironment(static fn (): string => 'production');
        $found = $check->run();
        $this->assertSame(Diagnosis::WARN, $found[0]->state, 'and a warning in production');
        $this->assertStringContainsString('Set CACHE_STORE', $found[0]->detail);

        config(['cache.default' => 'database']);
        $found = $check->run();
        $this->assertSame(Diagnosis::OK, $found[0]->state);
        $this->assertStringContainsString('5 cache groups', $found[0]->detail);
        $this->assertStringContainsString('webx:cache:prune', $found[0]->detail);

        config(['webx-cache.enabled' => false]);
        $found = $check->run();
        $this->assertSame(Diagnosis::WARN, $found[0]->state);
        $this->assertStringContainsString('webx-cache.enabled', $found[0]->detail);

        $this->assertContains(CacheStore::class, (new \ReflectionClassConstant(DoctorCommand::class, 'CHECKS'))->getValue());
    }

    #[Test]
    public function the_database_store_is_swept_hourly(): void
    {
        $events = collect($this->app->make(Schedule::class)->events())
            ->filter(static fn ($event): bool => str_contains((string) $event->command, 'webx:cache:prune'));

        $this->assertCount(1, $events);
        $this->assertSame('0 * * * *', $events->first()->expression);

        $this->artisan('webx:cache:prune')
            ->expectsOutputToContain('The array store (array) prunes itself')
            ->assertSuccessful();

        Schema::create('cache', static function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });
        Schema::create('cache_locks', static function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });
        DB::table('cache')->insert([
            ['key' => 'old', 'value' => 's:1:"x";', 'expiration' => time() - 10],
            ['key' => 'live', 'value' => 's:1:"y";', 'expiration' => time() + 3600],
        ]);
        DB::table('cache_locks')->insert([['key' => 'lock', 'owner' => 'me', 'expiration' => time() - 10]]);
        config(['cache.default' => 'database']);

        $this->artisan('webx:cache:prune')
            ->expectsOutputToContain('Pruned 2 expired rows.')
            ->assertSuccessful();

        $this->assertSame(['live'], DB::table('cache')->pluck('key')->all());
        $this->assertSame(0, DB::table('cache_locks')->count());
    }
}
