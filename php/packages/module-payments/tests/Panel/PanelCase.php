<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Panel;

use Illuminate\Foundation\Application;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;
use WebxUi\Orders\Models\Order;
use WebxUi\Payments\Tests\Online\OnlineCase;

/**
 * The section «Payments» of the panel and the order's tab: the module with the administrators of
 * `module-auth`, so the routes stand behind the panel's guard and a permission means what it means
 * on a site; «gateway A» on, its three methods with the test key.
 */
abstract class PanelCase extends OnlineCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), AuthServiceProvider::class];
    }

    /** @param  list<string>  $permissions */
    protected function signIn(array $permissions = ['payments.view', 'payments.manage']): CmsUser
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
        return rtrim('/api/cms/payments/'.$path, '/');
    }

    /** An order by transfer, awaiting payment: «Mark as paid» is its action. */
    protected function transferOrder(): Order
    {
        return $this->place($this->cart(), [
            'awaitPayment' => true,
            'blocks' => ['payment' => [
                'method' => 'bank-transfer',
                'type' => 'store.bank_transfer',
                'kind' => 'offline',
                'title' => ['en' => 'Bank transfer'],
                'price' => null,
                'instructions' => ['recipient' => ['en' => 'Shop A Ltd'], 'account' => ['en' => 'Account 0000']],
            ]],
        ]);
    }
}
