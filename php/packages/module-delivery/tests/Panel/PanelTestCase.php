<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests\Panel;

use Illuminate\Foundation\Application;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;
use WebxUi\Cache\CacheServiceProvider;
use WebxUi\Cart\CartServiceProvider;
use WebxUi\Delivery\DeliveryServiceProvider;
use WebxUi\Delivery\Tests\TestCase;
use WebxUi\Geography\GeographyServiceProvider;
use WebxUi\Localization\LocalizationServiceProvider;

/**
 * The section «Delivery» of the panel: the module with the administrators of `module-auth`, so
 * the routes stand behind the panel's guard and a permission means what it means on a site.
 */
abstract class PanelTestCase extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LocalizationServiceProvider::class,
            CacheServiceProvider::class,
            AdminServiceProvider::class,
            AuthServiceProvider::class,
            GeographyServiceProvider::class,
            CartServiceProvider::class,
            DeliveryServiceProvider::class,
        ];
    }

    /** @param  list<string>  $permissions */
    protected function signIn(array $permissions = ['delivery.view', 'delivery.manage']): CmsUser
    {
        static $count = 0;
        $count++;

        $user = CmsUser::query()->create([
            'name' => "Manager {$count}",
            'email' => "manager-{$count}@example.test",
            'password' => 'correct-horse-battery',
            'is_super' => false,
            'is_active' => true,
        ]);

        $role = Role::query()->create(['slug' => "manager-{$count}", 'name' => 'Manager', 'permissions' => $permissions]);
        $user->roles()->attach($role->getKey());
        $this->actingAs($user, 'cms');

        return $user;
    }

    protected function api(string $path = ''): string
    {
        return rtrim('/api/cms/delivery/'.$path, '/');
    }
}
