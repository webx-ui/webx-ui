<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Panel;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Access\DefaultRoles;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;
use WebxUi\Auth\Testing\AssertsPermissions;
use WebxUi\Payments\Tests\TestCase;
use WebxUi\Themes\ThemeServiceProvider;

/**
 * Nothing of the section is open to an administrator who holds no permission, and every permission
 * asked for is one a module declares (WEBX_UI_PERMISSIONS.md §15). The buyer's pages, the step and
 * the gateway's notifications are not panel routes: they are the site's (spec §4).
 *
 * On the module's own case, not the checkout's: the two test traits both name a helper `fill()`.
 */
final class AccessCoverageTest extends TestCase
{
    use AssertsPermissions;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        // The widgets ask the theme for its assets on every request through the kernel.
        return [ThemeServiceProvider::class, ...parent::getPackageProviders($app), AuthServiceProvider::class];
    }

    /** @param  list<string>  $permissions */
    private function signIn(array $permissions): void
    {
        $user = CmsUser::query()->create([
            'name' => 'Reader',
            'email' => 'reader@example.test',
            'password' => 'correct-horse-battery',
            'is_super' => false,
            'is_active' => true,
        ]);
        $role = Role::query()->create(['slug' => 'reader', 'name' => 'Reader', 'permissions' => $permissions]);
        $user->roles()->attach($role->getKey());
        $this->actingAs($user, 'cms');
    }

    private function api(string $path): string
    {
        return '/api/cms/payments/'.$path;
    }

    #[Test]
    public function every_route_asks_for_a_declared_permission(): void
    {
        $this->assertPanelRoutesNeedPermission(['WebxUi\\Payments\\Http\\Panel\\', 'api/cms/payments/']);
    }

    #[Test]
    public function reading_does_not_open_the_writes_nor_the_bodies(): void
    {
        $this->signIn(['payments.view']);

        $this->getJson($this->api('methods'))->assertOk();
        $this->getJson($this->api('attempts'))->assertOk();
        $this->getJson($this->api('exchange'))->assertOk();
        $this->putJson($this->api('methods/order'), ['ids' => []])->assertForbidden();
        $this->postJson($this->api('methods/1/test'))->assertForbidden();
        $this->putJson($this->api('methods/1'), ['values' => []])->assertForbidden();
        $this->get($this->api('exchange/1/request'))->assertForbidden();
        $this->postJson($this->api('attempts/1/check'))->assertForbidden();
    }

    #[Test]
    public function the_manager_of_orders_reads_the_payments_and_never_their_bodies(): void
    {
        $roles = $this->app->make(DefaultRoles::class)->definitions();

        $this->assertContains('payments.view', $roles['orders']);
        $this->assertNotContains('payments.exchange', $roles['orders']);
        $this->assertNotContains('payments.exchange', $roles['viewer']);
        $this->assertContains('payments.view', $roles['viewer']);
    }
}
