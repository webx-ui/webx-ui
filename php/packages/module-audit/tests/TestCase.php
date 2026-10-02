<?php

declare(strict_types=1);

namespace WebxUi\Audit\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Audit\AuditServiceProvider;
use WebxUi\Audit\Probes\CertificateReader;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Settings\SettingsServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // No test opens a socket: the certificate is whatever a test says it is.
        $this->app->instance(CertificateReader::class, new FakeCertificateReader);
        Http::preventStrayRequests();
    }

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
            SettingsServiceProvider::class,
            AuditServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.url', 'https://shop.example.com');
        $app['config']->set('app.env', 'production');
        $app['config']->set('app.debug', false);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('mail.default', 'smtp');
        $app['config']->set('queue.default', 'database');
        // Testbench has no public/storage; the test of that check asks for the link itself.
        $app['config']->set('filesystems.links', []);
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true]]);
        $app['config']->set('database.connections.testing.foreign_key_constraints', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function admin(array $permissions = ['audit.view', 'audit.run']): CmsUser
    {
        static $count = 0;
        $count++;

        $user = CmsUser::query()->create([
            'name' => 'Admin',
            'email' => "admin-{$count}@example.test",
            'password' => 'correct-horse-battery',
            'is_super' => false,
            'is_active' => true,
        ]);

        $role = Role::query()->create(['slug' => "auditor-{$count}", 'name' => 'Auditor', 'permissions' => $permissions]);
        $user->roles()->attach($role->getKey());

        return $user;
    }
}
