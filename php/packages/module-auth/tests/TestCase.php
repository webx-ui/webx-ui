<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LocalizationServiceProvider::class,
            AdminServiceProvider::class,
            McpServiceProvider::class,
            AuthServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();
    }

    protected function admin(string $email = 'admin@example.test', bool $super = false, bool $active = true): CmsUser
    {
        return CmsUser::query()->create([
            'name' => 'Admin',
            'email' => $email,
            'password' => 'correct-horse-battery',
            'is_super' => $super,
            'is_active' => $active,
        ]);
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function role(string $slug, array $permissions): Role
    {
        return Role::query()->create([
            'slug' => $slug,
            'name' => ucfirst($slug),
            'permissions' => $permissions,
        ]);
    }
}
