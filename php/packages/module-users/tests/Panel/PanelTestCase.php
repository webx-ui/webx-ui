<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests\Panel;

use Illuminate\Foundation\Application;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Users\Panel\UsersModule;
use WebxUi\Users\Tests\TestCase;

/**
 * The module with the panel's administrators and the site's settings: «Customers» as an
 * administrator reaches it.
 */
abstract class PanelTestCase extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), AuthServiceProvider::class, SettingsServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // sqlite ignores foreign keys unless asked, and a cascade that does not exist would pass.
        $app['config']->set('database.connections.testing.foreign_key_constraints', true);
    }

    /** @param  list<string>  $permissions */
    protected function administrator(array $permissions = [UsersModule::VIEW, UsersModule::MANAGE, UsersModule::SETTINGS]): CmsUser
    {
        static $count = 0;
        $count++;

        $user = CmsUser::query()->create([
            'name' => 'Manager',
            'email' => "manager-{$count}@example.test",
            'password' => 'correct-horse-battery',
            'is_super' => false,
            'is_active' => true,
        ]);

        $role = Role::query()->create(['slug' => "manager-{$count}", 'name' => 'Manager', 'permissions' => $permissions]);
        $user->roles()->attach($role->getKey());

        return $user;
    }

    /** @param  list<string>|null  $permissions */
    protected function asAdministrator(?array $permissions = null): static
    {
        return $this->actingAs($permissions === null ? $this->administrator() : $this->administrator($permissions), 'cms');
    }

    protected function api(string|int $path = ''): string
    {
        return rtrim('/api/cms/users/'.$path, '/');
    }
}
