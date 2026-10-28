<?php

declare(strict_types=1);

namespace WebxUi\Cache\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cache\CacheGroups;
use WebxUi\Cache\Tests\Fixtures\Widget;

/**
 * Cache on versioned keys (WEBX_UI_CACHE.md §2–§3): a group moves when its model is saved or
 * when told to, an entry goes with any group it was built from, a lost version never meets its
 * past, a language has entries of its own, and a transaction neither stores nor moves anything
 * before it commits — on the `array`, `file` and `database` stores alike.
 */
final class CacheGroupsTest extends TestCase
{
    private ?string $files = null;

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('widgets', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->softDeletes();
            $table->timestamps();
        });

        // The `database` store's own tables, as Laravel's migration makes them.
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
    }

    protected function tearDown(): void
    {
        if ($this->files !== null) {
            (new Filesystem)->deleteDirectory($this->files);
        }

        parent::tearDown();
    }

    /** @return array<string, array{string}> */
    public static function stores(): array
    {
        return ['array' => ['array'], 'file' => ['file'], 'database' => ['database']];
    }

    #[Test]
    public function a_version_starts_away_from_one_and_holds_until_moved(): void
    {
        $groups = $this->fresh();
        $groups->declare('menu', 'Menu');

        $first = $groups->version('menu');

        $this->assertGreaterThan(1_000, $first, 'a version seeded at 1 would meet the keys of a version lost before it');
        $this->assertSame($first, $groups->version('menu'));
        $this->assertSame($first, $this->fresh()->version('menu'), 'a fresh instance reads the same version from the store');

        $groups->bump('menu');

        $this->assertSame($first + 1, $groups->version('menu'));
        $this->assertSame($first + 1, $this->fresh()->version('menu'));
    }

    #[Test]
    public function a_group_has_to_be_declared_before_it_is_used(): void
    {
        $groups = $this->app->make(CacheGroups::class);
        $groups->declare('menu');

        $this->assertSame(['menu'], $groups->ids(), 'the library declares nothing of its own: every group has an owner');

        try {
            $groups->bump('menus');
            $this->fail('An undeclared group must be refused: it is a typo nobody would notice otherwise.');
        } catch (InvalidArgumentException $failure) {
            $this->assertStringContainsString('[menus] is not a declared cache group', $failure->getMessage());
            $this->assertStringContainsString('menu', $failure->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $groups->declare('Menu Items');
    }

    #[Test]
    #[DataProvider('stores')]
    public function an_entry_goes_when_any_group_it_was_built_from_moves(string $store): void
    {
        $groups = $this->fresh($store);
        $groups->declare('menu');
        $groups->declare('settings');
        $groups->declare('seo');

        $built = 0;
        $header = function () use ($groups, &$built): string {
            return $groups->remember('header', ['menu', 'settings'], function () use (&$built): string {
                $built++;

                return "header #{$built}";
            });
        };

        $this->assertSame('header #1', $header());
        $this->assertSame('header #1', $header(), 'the second read comes from the cache');
        $this->assertSame(1, $built);

        $groups->bump('seo');
        $this->assertSame('header #1', $header(), 'a group the entry does not depend on changes nothing');

        $groups->bump('settings');
        $this->assertSame('header #2', $header(), 'the settings moved: built again');

        $groups->bump('menu');
        $this->assertSame('header #3', $header());
        $this->assertSame(3, $built);

        // Another process, with no memo: it reads the versions the first one moved.
        $this->assertSame('header #3', $this->fresh($store)->remember('header', ['menu', 'settings'], static fn (): string => 'header #4'));
    }

    #[Test]
    public function saving_deleting_restoring_a_model_moves_its_groups(): void
    {
        $groups = $this->fresh();
        $groups->bumpOn('menu', Widget::class);
        $groups->bumpOn('sitemap', Widget::class);
        $groups->declare('settings');

        $menu = $groups->version('menu');
        $sitemap = $groups->version('sitemap');
        $settings = $groups->version('settings');

        $widget = Widget::query()->create(['name' => 'Home']);
        $this->assertSame([$menu + 1, $sitemap + 1, $settings], [$groups->version('menu'), $groups->version('sitemap'), $groups->version('settings')]);

        $widget->update(['name' => 'Start']);
        $this->assertSame($menu + 2, $groups->version('menu'));

        $widget->delete();
        $this->assertSame($menu + 3, $groups->version('menu'), 'a soft delete is a `deleted` event');

        $widget->restore();
        // `restore()` fires `restored` and, through its save, `saved` too: two moves, both honest.
        $this->assertGreaterThanOrEqual($menu + 4, $groups->version('menu'));

        $before = $groups->version('menu');
        $widget->forceDelete();
        $this->assertGreaterThan($before, $groups->version('menu'));

        $this->assertSame($settings, $groups->version('settings'), 'a group without the model stays');
        $this->assertSame(['Widget'], array_map(static fn (string $model): string => substr($model, (int) strrpos($model, '\\') + 1), $groups->all()['menu']->models()));
    }

    #[Test]
    public function a_mass_update_moves_nothing_until_told(): void
    {
        $groups = $this->fresh();
        $groups->bumpOn('menu', Widget::class);
        Widget::query()->create(['name' => 'Home']);
        $version = $groups->version('menu');

        Widget::query()->update(['name' => 'Start']);
        $this->assertSame($version, $groups->version('menu'), 'no Eloquent event, no move: the writer says `bump()` itself');

        $groups->bump('menu');
        $this->assertSame($version + 1, $groups->version('menu'));
    }

    #[Test]
    public function a_lost_version_never_meets_the_entries_of_its_past(): void
    {
        $groups = $this->fresh();
        $groups->declare('menu');

        $groups->remember('tree', ['menu'], static fn (): string => 'old tree');
        $key = $groups->key('tree', ['menu']);
        $this->assertSame('old tree', Cache::get($key));

        // The version evicted; the entries built on it still there.
        Cache::forget(CacheGroups::PREFIX.'menu');
        $fresh = $this->fresh();

        $this->assertNotSame($key, $fresh->key('tree', ['menu']), 'reseeded at random, not at 1 or at the old number');
        $this->assertSame('new tree', $fresh->remember('tree', ['menu'], static fn (): string => 'new tree'));
    }

    #[Test]
    public function every_language_has_entries_of_its_own_unless_the_data_is_the_same_in_all(): void
    {
        $groups = $this->fresh();
        $groups->declare('menu');

        $this->app->setLocale('en');
        $this->assertSame('en tree', $groups->remember('tree', ['menu'], static fn (): string => 'en tree'));

        $this->app->setLocale('ru');
        $this->assertSame('ru tree', $groups->remember('tree', ['menu'], static fn (): string => 'ru tree'));
        $this->assertSame('ru tree', $groups->remember('tree', ['menu'], static fn (): string => 'again'));

        $this->app->setLocale('en');
        $this->assertSame('en tree', $groups->remember('tree', ['menu'], static fn (): string => 'again'));

        $this->assertSame('all', $groups->remember('rows', ['menu'], static fn (): string => 'all', localized: false));
        $this->app->setLocale('ru');
        $this->assertSame('all', $groups->remember('rows', ['menu'], static fn (): string => 'ru rows', localized: false));

        $groups->bump('menu');
        $this->assertSame('ru rows', $groups->remember('rows', ['menu'], static fn (): string => 'ru rows', localized: false));
    }

    #[Test]
    public function inside_a_transaction_nothing_is_stored_and_the_move_waits_for_the_commit(): void
    {
        $groups = $this->fresh();
        $groups->bumpOn('menu', Widget::class);
        $groups->declare('settings');
        $version = $groups->version('menu');
        $settings = $groups->version('settings');

        DB::transaction(function () use ($groups, $version, $settings): void {
            Widget::query()->create(['name' => 'Home']);
            Widget::query()->create(['name' => 'About']);
            $groups->bump('settings');

            $this->assertSame($version, $this->fresh()->version('menu'), 'another process sees the old version until the commit');
            $this->assertSame($settings, $this->fresh()->version('settings'));

            $this->assertSame('inside', $groups->remember('tree', ['menu'], static fn (): string => 'inside'));
            $this->assertSame('inside again', $groups->remember('tree', ['menu'], static fn (): string => 'inside again'), 'not stored: the rows may still roll back');
        });

        $this->assertSame($version + 1, $groups->version('menu'), 'two saves in one transaction are one move, after the commit');
        $this->assertSame($settings + 1, $groups->version('settings'));

        // A rehearsal: written, read, rolled back — nothing of it stays.
        $moved = $groups->version('menu');
        DB::beginTransaction();
        Widget::query()->create(['name' => 'Never']);
        $groups->bump('settings');
        DB::rollBack();

        $this->assertSame($moved, $groups->version('menu'));
        $this->assertSame($settings + 1, $groups->version('settings'));
        $this->assertSame(2, Widget::query()->count(), 'the rolled-back row is gone; the two committed ones stay');

        // The next transaction is not held back by the one that rolled back.
        DB::transaction(static fn () => Widget::query()->create(['name' => 'Home']));
        $this->assertSame($moved + 1, $groups->version('menu'));
    }

    #[Test]
    public function a_hook_runs_on_a_reset_by_code_and_not_on_a_models_own_move(): void
    {
        $groups = $this->fresh();
        $groups->bumpOn('settings', Widget::class);
        $forgotten = [];
        $groups->onReset('settings', function () use (&$forgotten): void {
            $forgotten[] = true;
        });

        $groups->bump('settings');
        $this->assertCount(1, $forgotten);

        // The model's own cache forgets itself on this event already; a second forget would be
        // the subscriber's precise one made coarse.
        Widget::query()->create(['name' => 'x']);
        $this->assertCount(1, $forgotten);

        $groups->bumpAll();
        $this->assertCount(2, $forgotten);

        // Queued behind a commit, the reset keeps its hooks; the model's move alone would not.
        DB::transaction(function () use ($groups): void {
            Widget::query()->create(['name' => 'y']);
            $groups->bump('settings');
        });
        $this->assertCount(3, $forgotten);
    }

    #[Test]
    public function the_versions_are_read_once_a_request_in_one_call(): void
    {
        $groups = $this->fresh('database');
        $groups->declare('menu');
        $groups->declare('settings');
        $groups->version('menu');

        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        $groups->version('settings');
        $groups->key('header', ['menu', 'settings', 'locales']);
        $groups->versions('theme', 'routes');

        $this->assertSame(0, $queries, 'every declared group was read with the first one');

        $groups->forgetMemo();
        $groups->version('menu');
        $this->assertSame(1, $queries, 'the next request reads them all again, in one `many()`');
    }

    #[Test]
    public function the_entries_every_page_reads_come_with_the_versions_in_one_call(): void
    {
        $groups = $this->fresh('database');
        $groups->declare('menu');
        $groups->declare('settings');
        $groups->prefetch('settings', ['settings'], localized: false);
        $groups->prefetch('types', ['menu']);
        $groups->remember('settings', ['settings'], static fn (): string => 'the settings', localized: false);
        $groups->remember('types', ['menu'], static fn (): string => 'the types');
        $groups->forgetMemo();

        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        // The next request: the versions and both entries in two calls, whichever is asked first.
        $this->assertSame('the types', $groups->remember('types', ['menu'], static fn (): string => 'built again'));
        $this->assertSame('the settings', $groups->remember('settings', ['settings'], static fn (): string => 'built again', localized: false));
        $this->assertSame(2, $queries, 'the versions, then every declared entry — one `many()` each');

        // A miss stays a miss: what the store has not got is computed by whoever asks for it.
        $groups->prefetch('missing', ['menu']);
        $groups->forgetMemo();
        $this->assertSame('computed', $groups->remember('missing', ['menu'], static fn (): string => 'computed'));

        // Moved, the old entry is not what the next request warms up with.
        $groups->bump('menu');
        $groups->forgetMemo();
        $this->assertSame('rebuilt', $groups->remember('types', ['menu'], static fn (): string => 'rebuilt'));
    }

    #[Test]
    public function switched_off_it_computes_every_time(): void
    {
        config(['webx-cache.enabled' => false]);
        $groups = $this->fresh();
        $groups->declare('menu');

        $this->assertSame('one', $groups->remember('tree', ['menu'], static fn (): string => 'one'));
        $this->assertSame('two', $groups->remember('tree', ['menu'], static fn (): string => 'two'));
    }

    #[Test]
    public function strict_it_refuses_an_object_in_a_value_and_keeps_arrays_and_scalars(): void
    {
        $this->assertTrue(config('webx-cache.strict'), 'the tests of the monorepo run strict (phpunit.xml.dist)');
        $groups = $this->fresh();

        $plain = ['counts' => ['black' => 3], 'min' => 1.5, 'open' => true, 'rank' => null];
        $this->assertSame($plain, $groups->remember('plain', ['menu'], static fn (): array => $plain));

        foreach ([
            'remember' => static fn () => $groups->remember('facets', ['menu'], static fn (): array => ['color' => ['result' => new Widget]]),
            'put' => static fn () => $groups->put('facets', ['menu'], ['color' => ['result' => new Widget]]),
        ] as $how => $store) {
            try {
                $store();
                $this->fail("{$how}() stored an object.");
            } catch (LogicException $refused) {
                $this->assertStringContainsString('"facets" holds '.Widget::class.' at "color.result"', $refused->getMessage(), $how);
            }
        }

        // Off — a site's default — it is the store's business.
        config(['webx-cache.strict' => false]);
        $this->assertInstanceOf(Widget::class, $groups->remember('object', ['menu'], static fn (): Widget => new Widget));
    }

    #[Test]
    public function a_long_key_is_hashed_rather_than_cut(): void
    {
        $groups = $this->fresh();
        $groups->declare('menu');
        $name = str_repeat('a-very-long-name.', 12);

        $key = $groups->key($name, ['menu', 'locales', 'routes', 'screens', 'theme']);

        $this->assertLessThan(255, strlen($key));
        $this->assertStringStartsWith('webx:'.$name.':', $key);
        $this->assertNotSame($key, tap($groups)->bump('menu')->key($name, ['menu', 'locales', 'routes', 'screens', 'theme']));
    }

    /** A fresh registry on the store named, as another process would have it: no memo, the same groups declared. */
    private function fresh(string $store = 'array'): CacheGroups
    {
        if ($store === 'file') {
            $this->files ??= sys_get_temp_dir().'/webx-cache-'.bin2hex(random_bytes(4));
            config(['cache.stores.file.path' => $this->files]);
        }

        // The `array` store is the process's memory: forgetting the driver would be a new process
        // with a new, empty memory, which is not what another instance in the same one sees.
        if (config('cache.default') !== $store) {
            config(['cache.default' => $store]);
            $this->app->forgetInstance('cache.store');
            $this->app->make('cache')->forgetDriver($store);
        }

        return $this->declared(new CacheGroups(
            $this->app->make('cache.store'),
            $this->app->make('events'),
            $this->app->make('db'),
            $this->app->make('config'),
            $this->app,
        ));
    }

    private function declared(CacheGroups $groups): CacheGroups
    {
        foreach ([CacheGroups::LOCALES, CacheGroups::ROUTES, CacheGroups::SCREENS, CacheGroups::THEME, 'menu', 'settings', 'seo', 'sitemap'] as $group) {
            $groups->declare($group);
        }

        return $groups;
    }
}
