<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Auth\Models\Role;

/**
 * The ready-made roles (WEBX_UI_PERMISSIONS.md §12): made of the modules a site has, never over
 * a role the site already has, never with the keys to the panel itself.
 */
final class DefaultRolesTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = __DIR__.'/../database/migrations/2026_10_18_000001_seed_default_roles.php';

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

    private function up(): void
    {
        $migration = require self::MIGRATION;

        if (! $migration instanceof Migration || ! method_exists($migration, 'up')) {
            self::fail('The migration has no up().');
        }

        $migration->up();
    }
}
