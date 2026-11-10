<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use Illuminate\Database\Migrations\Migration;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\HasDefaultRoles;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Auth\Access\DefaultRoles;
use WebxUi\Auth\Doctor\DefaultRolesOwed;
use WebxUi\Auth\Models\Role;

/**
 * The ready-made roles (WEBX_UI_PERMISSIONS.md §12): made of the modules a site has, never over
 * a role the site already has, never with the keys to the panel itself; topped up with a module
 * installed after them, never with what the owner took away.
 */
final class DefaultRolesTest extends TestCase
{
    private const MIGRATION = __DIR__.'/../database/migrations/2026_10_18_000001_seed_default_roles.php';

    private const TOP_UP = __DIR__.'/../database/migrations/2026_10_19_000000_top_up_default_roles.php';

    #[Test]
    public function the_roles_hold_only_what_the_installed_modules_declare(): void
    {
        $this->install('pages', ['pages.view', 'pages.manage']);
        $this->install('tasks', ['tasks.view', 'tasks.manage']);

        $this->artisan('webx:roles:defaults')->assertSuccessful();

        $roles = Role::query()->pluck('permissions', 'slug');

        // Inbox, the catalogue and the media library are not here, so neither are the roles
        // made only of them; the editor is made of what there is.
        $this->assertSame(['editor', 'seo', 'developer', 'viewer'], $roles->keys()->all());
        $this->assertSame(['pages.view', 'pages.manage'], $roles['editor']);
        $this->assertSame(['pages.view'], $roles['seo']);
        $this->assertSame(['cache.view', 'cache.reset'], $roles['developer']);
        $this->assertSame(['cache.view', 'pages.view'], $roles['viewer']);
        $this->assertSame('Editor', Role::query()->where('slug', 'editor')->value('name'));

        foreach ($roles->flatten() as $permission) {
            $this->assertDoesNotMatchRegularExpression('/^(admins|roles|tasks|debug)\./', $permission);
        }
    }

    #[Test]
    public function a_role_the_site_has_is_left_as_it_is(): void
    {
        $this->install('pages', ['pages.view', 'pages.manage']);
        $this->role('editor', ['pages.view']);

        $this->artisan('webx:roles:defaults')->assertSuccessful();
        $this->artisan('webx:roles:defaults')->expectsOutputToContain('already there')->assertSuccessful();

        $this->assertSame(['pages.view'], Role::query()->where('slug', 'editor')->firstOrFail()->permissions);
        $this->assertSame(1, Role::query()->where('slug', 'viewer')->count());
    }

    #[Test]
    public function a_new_site_gets_them_with_its_first_administrator(): void
    {
        $this->artisan('webx:admin', ['--name' => 'Ada', '--email' => 'ada@example.test'])
            ->expectsQuestion('Password', 'correct-horse-battery')
            ->assertSuccessful();

        $this->assertSame(['developer', 'viewer'], Role::query()->orderBy('id')->pluck('slug')->all());

        // The second one changes nothing.
        Role::query()->where('slug', 'viewer')->delete();
        $this->artisan('webx:admin', ['--name' => 'Grace', '--email' => 'grace@example.test'])
            ->expectsQuestion('Password', 'correct-horse-battery')
            ->assertSuccessful();

        $this->assertSame(['developer'], Role::query()->pluck('slug')->all());
    }

    #[Test]
    public function the_migration_brings_them_to_a_running_site_only(): void
    {
        $this->up();
        $this->assertSame(0, Role::query()->count());

        $this->admin();
        $this->up();
        $this->assertSame(['developer', 'viewer'], Role::query()->orderBy('id')->pluck('slug')->all());
    }

    // -- Topping up ----------------------------------------------------------------------------

    #[Test]
    public function a_module_installed_after_the_roles_is_topped_up_into_them(): void
    {
        $this->install('pages', ['pages.view', 'pages.manage']);
        $this->artisan('webx:roles:defaults')->assertSuccessful();

        $this->install('snippets', ['snippets.manage']);
        $this->install('notifications', ['notifications.view', 'notifications.manage']);

        $this->artisan('webx:roles:defaults', ['--top-up' => true])
            ->expectsOutputToContain('developer')
            ->assertSuccessful();

        $roles = Role::query()->pluck('permissions', 'slug');

        $this->assertSame(['cache.view', 'cache.reset', 'snippets.manage', 'notifications.view', 'notifications.manage'], $roles['developer']);
        $this->assertSame(['cache.view', 'pages.view', 'notifications.view'], $roles['viewer']);
        $this->assertSame(['pages.view', 'pages.manage'], $roles['editor']);

        // Topping up creates nothing: `leads` could be made now, but that is a person's call.
        $this->assertFalse(Role::query()->where('slug', 'leads')->exists());

        $this->artisan('webx:roles:defaults')->assertSuccessful();
        $this->assertSame(['notifications.view'], Role::query()->where('slug', 'leads')->firstOrFail()->permissions);
    }

    #[Test]
    public function a_permission_the_owner_took_away_does_not_come_back(): void
    {
        $this->artisan('webx:roles:defaults')->assertSuccessful();

        $developer = Role::query()->where('slug', 'developer')->firstOrFail();
        $developer->permissions = ['cache.view'];
        $developer->save();

        $this->install('snippets', ['snippets.manage']);
        $this->artisan('webx:roles:defaults')->assertSuccessful();
        $this->artisan('webx:roles:defaults')->assertSuccessful();

        $this->assertSame(['cache.view', 'snippets.manage'], $developer->refresh()->permissions);
    }

    #[Test]
    public function the_sites_own_roles_and_what_is_never_handed_out_stay_as_they_are(): void
    {
        $own = $this->role('developer', ['cache.view']);
        $this->artisan('webx:roles:defaults')->assertSuccessful();

        $this->install('snippets', ['snippets.manage']);
        $this->install('debug', ['debug.view']);
        $this->install('tasks', ['tasks.view', 'tasks.manage']);
        $this->artisan('webx:roles:defaults')->assertSuccessful();

        $this->assertSame(['cache.view'], $own->refresh()->permissions);
        $this->assertNull($own->offered);
        $this->assertSame(['cache.view'], Role::query()->where('slug', 'viewer')->firstOrFail()->permissions);
    }

    #[Test]
    public function running_it_again_changes_nothing(): void
    {
        $this->artisan('webx:roles:defaults')->assertSuccessful();
        $this->install('snippets', ['snippets.manage']);
        $this->artisan('webx:roles:defaults')->assertSuccessful();

        $before = Role::query()->orderBy('id')->get(['slug', 'permissions', 'offered'])->toArray();

        $this->artisan('webx:roles:defaults')->expectsOutputToContain('already there')->assertSuccessful();

        $this->assertSame($before, Role::query()->orderBy('id')->get(['slug', 'permissions', 'offered'])->toArray());
    }

    #[Test]
    public function a_dry_run_says_what_it_would_do_and_writes_nothing(): void
    {
        $this->artisan('webx:roles:defaults')->assertSuccessful();
        $this->install('snippets', ['snippets.manage']);
        $this->install('inbox', ['inbox.view', 'inbox.update']);

        $this->artisan('webx:roles:defaults', ['--dry-run' => true])
            ->expectsOutputToContain('snippets.manage')
            ->expectsOutputToContain('inbox.view, inbox.update')
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertSame(['cache.view', 'cache.reset'], Role::query()->where('slug', 'developer')->firstOrFail()->permissions);
        $this->assertFalse(Role::query()->where('slug', 'leads')->exists());
    }

    #[Test]
    public function a_site_whose_roles_were_made_before_they_remembered_is_brought_up(): void
    {
        // The site as the release before this one left it: roles made of the modules then, no
        // `offered`; one of them edited by the owner, one the site's own under a ready-made code.
        $this->admin();
        $this->install('pages', ['pages.view', 'pages.manage']);
        $this->role('developer', ['cache.view']);
        $this->role('viewer', ['cache.view', 'pages.view']);
        $this->role('editor', ['pages.view', 'admins.view']);

        $this->install('snippets', ['snippets.manage']);
        $this->install('notifications', ['notifications.view', 'notifications.manage']);

        $this->migrate(self::TOP_UP);

        $roles = Role::query()->get()->keyBy('slug');

        // Cache was there when the developer was made, so the reset it lacks was taken away.
        $this->assertSame(['cache.view', 'snippets.manage', 'notifications.view', 'notifications.manage'], $roles['developer']->permissions);
        $this->assertSame(['cache.view', 'pages.view', 'notifications.view'], $roles['viewer']->permissions);
        $this->assertSame(['pages.view', 'admins.view'], $roles['editor']->permissions);
        $this->assertNull($roles['editor']->offered);

        // Not created by the migration either: missing may mean deleted.
        $this->assertFalse(Role::query()->where('slug', 'leads')->exists());

        // And the reset stays away afterwards.
        $this->artisan('webx:roles:defaults')->assertSuccessful();
        $this->assertNotContains('cache.reset', $roles['developer']->refresh()->permissions);
    }

    #[Test]
    public function the_doctor_names_the_roles_behind_the_modules(): void
    {
        $this->artisan('webx:roles:defaults')->assertSuccessful();

        $check = $this->app->make(DefaultRolesOwed::class);
        $this->assertSame(Diagnosis::OK, $this->state($check));

        $this->install('snippets', ['snippets.manage']);
        $found = $check->run();

        $this->assertSame(Diagnosis::WARN, $found[0]->state);
        $this->assertStringContainsString('developer ← snippets.manage', $found[0]->detail);
        $this->assertStringContainsString('webx:roles:defaults --top-up', $found[0]->detail);
        $this->assertContains(DefaultRolesOwed::class, $this->app->make(DoctorChecks::class)->all());

        $this->artisan('webx:roles:defaults', ['--top-up' => true])->assertSuccessful();
        $this->assertSame(Diagnosis::OK, $this->state($check));

        // A ready-made role the owner deleted is not something to nag about.
        Role::query()->where('slug', 'viewer')->delete();
        $afterDelete = $check->run();
        $this->assertSame(Diagnosis::OK, $afterDelete[0]->state);
    }

    // -- Named by the module ---------------------------------------------------------------------

    #[Test]
    public function a_module_that_names_its_permissions_is_in_the_roles_it_names(): void
    {
        $this->install('pages', ['pages.view', 'pages.manage']);
        $this->contribute('offices', ['offices.view', 'offices.manage'], [
            'editor' => ['offices.view', 'offices.manage'],
            'seo' => ['offices.view'],
            'developer' => ['offices.manage'],
        ]);

        $this->artisan('webx:roles:defaults')->assertSuccessful();

        $roles = Role::query()->pluck('permissions', 'slug');

        $this->assertSame(['pages.view', 'pages.manage', 'offices.view', 'offices.manage'], $roles['editor']);
        $this->assertSame(['pages.view', 'offices.view'], $roles['seo']);
        $this->assertSame(['cache.view', 'cache.reset', 'offices.manage'], $roles['developer']);
        $this->assertSame(['cache.view', 'offices.view', 'pages.view'], $roles['viewer']);
    }

    #[Test]
    public function a_module_that_names_its_permissions_is_topped_up_and_what_was_taken_stays_away(): void
    {
        $this->install('pages', ['pages.view', 'pages.manage']);
        $this->artisan('webx:roles:defaults')->assertSuccessful();

        $this->contribute('offices', ['offices.view', 'offices.manage'], ['editor' => ['offices.view', 'offices.manage']]);

        $this->artisan('webx:roles:defaults', ['--top-up' => true])
            ->expectsOutputToContain('offices.view, offices.manage')
            ->assertSuccessful();

        $editor = Role::query()->where('slug', 'editor')->firstOrFail();
        $this->assertSame(['pages.view', 'pages.manage', 'offices.view', 'offices.manage'], $editor->permissions);

        $editor->permissions = ['pages.view', 'pages.manage', 'offices.view'];
        $editor->save();

        $this->artisan('webx:roles:defaults', ['--top-up' => true])->assertSuccessful();
        $this->artisan('webx:roles:defaults')->assertSuccessful();

        $this->assertSame(['pages.view', 'pages.manage', 'offices.view'], $editor->refresh()->permissions);
    }

    #[Test]
    public function a_module_names_only_its_own_permissions_and_only_in_the_three_roles(): void
    {
        $this->install('pages', ['pages.view', 'pages.manage']);
        $this->install('inbox', ['inbox.view', 'inbox.update']);
        $this->install('tasks', ['tasks.view', 'tasks.manage']);
        $before = $this->app->make(DefaultRoles::class)->definitions();

        // Somebody else's permission, a role it may not name, a role that is not there, and a
        // key to the panel declared as its own: none of them gets in.
        $this->contribute('offices', ['offices.view', 'offices.manage', 'debug.view'], [
            'editor' => ['offices.manage', 'pages.view', 'inbox.update', 'debug.view'],
            'leads' => ['offices.view'],
            'owner' => ['offices.manage'],
        ]);

        $after = $this->app->make(DefaultRoles::class)->definitions();

        $this->assertSame([...$before['editor'], 'offices.manage', 'debug.view'], $after['editor']);
        $this->assertSame($before['leads'], $after['leads']);
        $this->assertArrayNotHasKey('owner', $after);

        $this->artisan('webx:roles:defaults')->assertSuccessful();

        $this->assertSame(['pages.view', 'pages.manage', 'offices.manage'], Role::query()->where('slug', 'editor')->firstOrFail()->permissions);
        $this->assertSame(['inbox.view', 'inbox.update'], Role::query()->where('slug', 'leads')->firstOrFail()->permissions);
    }

    #[Test]
    public function the_roles_of_the_modules_on_the_list_are_what_they_were(): void
    {
        $this->install('pages', ['pages.view', 'pages.manage']);
        $this->install('faq', ['faq.view', 'faq.manage', 'faq.categories.manage']);
        $this->install('seo', ['seo.view', 'seo.manage']);
        $before = $this->app->make(DefaultRoles::class)->definitions();

        // A module that names nothing, or names an empty list, changes nothing.
        $this->contribute('offices', ['offices.view'], []);
        $this->install('notes', ['notes.view']);

        $after = $this->app->make(DefaultRoles::class)->definitions();

        foreach (['editor', 'seo', 'leads', 'catalog', 'developer'] as $slug) {
            $this->assertSame($before[$slug], $after[$slug], $slug);
        }

        $this->assertEqualsCanonicalizing([...$before['viewer'], 'offices.view', 'notes.view'], $after['viewer']);
    }

    private function state(DefaultRolesOwed $check): string
    {
        return $check->run()[0]->state;
    }

    /**
     * @param  list<string>  $permissions
     */
    private function install(string $id, array $permissions): void
    {
        $this->app->make(ModuleRegistry::class)->register(new class($id, $permissions) extends AbstractModule
        {
            /**
             * @param  list<string>  $declared
             */
            public function __construct(private readonly string $key, private readonly array $declared) {}

            public function id(): string
            {
                return $this->key;
            }

            /**
             * @return list<string>
             */
            public function permissions(): array
            {
                return $this->declared;
            }
        });
    }

    /**
     * @param  list<string>  $permissions
     * @param  array<string, list<string>>  $roles
     */
    private function contribute(string $id, array $permissions, array $roles): void
    {
        $this->app->make(ModuleRegistry::class)->register(new class($id, $permissions, $roles) extends AbstractModule implements HasDefaultRoles
        {
            /**
             * @param  list<string>  $declared
             * @param  array<string, list<string>>  $roles
             */
            public function __construct(private readonly string $key, private readonly array $declared, private readonly array $roles) {}

            public function id(): string
            {
                return $this->key;
            }

            /**
             * @return list<string>
             */
            public function permissions(): array
            {
                return $this->declared;
            }

            /**
             * @return array<string, list<string>>
             */
            public function defaultRoles(): array
            {
                return $this->roles;
            }
        });
    }

    private function up(): void
    {
        $this->migrate(self::MIGRATION);
    }

    private function migrate(string $file): void
    {
        $migration = require $file;

        if (! $migration instanceof Migration || ! method_exists($migration, 'up')) {
            self::fail('The migration has no up().');
        }

        $migration->up();
    }
}
