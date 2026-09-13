<?php

declare(strict_types=1);

namespace WebxUi\Media\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Media\MediaServiceProvider;
use WebxUi\NestedSet\NestedSetServiceProvider;

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
            NestedSetServiceProvider::class,
            AdminServiceProvider::class,
            // The library is not public, and the permissions it declares are checked by the
            // auth module's middleware: the two are installed together.
            AuthServiceProvider::class,
            MediaServiceProvider::class,
        ];
    }

    /** Somebody who may do everything, which is what most of these tests are not about. */
    protected function actingAsAdmin(bool $super = true): CmsUser
    {
        $user = CmsUser::query()->create([
            'name' => 'Admin',
            'email' => 'admin'.uniqid().'@webx.test',
            'password' => 'correct-horse-battery-staple',
            'is_super' => $super,
        ]);

        // Refreshed on purpose: `is_active` is a database default, so the instance `create()`
        // hands back has it as null — and the panel's own middleware turns an account that is
        // not active away with a 403 that looks exactly like a missing permission.
        $user->refresh();

        $this->actingAs($user, (string) config('webx-auth.guard'));

        return $user;
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function grant(CmsUser $user, array $permissions): void
    {
        $role = Role::query()->create([
            'slug' => 'role-'.uniqid(),
            'name' => 'Role',
            'permissions' => $permissions,
        ]);

        $user->roles()->attach($role);
        $user->load('roles');
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');

        // The dictionary is cached in a real installation; a test that reads it wants the file
        // it just wrote, not what the previous test left behind.
        $app['config']->set('webx-localization.cache.enabled', false);
    }
}
