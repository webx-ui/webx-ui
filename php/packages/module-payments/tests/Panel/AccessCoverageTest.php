<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Panel;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Access\DefaultRoles;
use WebxUi\Auth\Access\PermissionMatrix;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;
use WebxUi\Auth\Testing\AssertsPermissions;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Payments\Panel\PaymentsSection;
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
        return [ThemeServiceProvider::class, ...parent::getPackageProviders($app), AuthServiceProvider::class, McpServiceProvider::class];
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

    /** @return array<string, bool> the section's entries of the manifest: id → whether it opens */
    private function entries(): array
    {
        $entries = [];

        foreach ($this->getJson('/api/cms/manifest')->assertOk()->json('data.modules') as $module) {
            if ($module['group'] === PaymentsSection::GROUP) {
                $entries[$module['id']] = $module['allowed'];
            }
        }

        return $entries;
    }

    private function api(string $path): string
    {
        return '/api/cms/payments/'.$path;
    }

    /** The roles' matrix names the section, not its first entry «Methods», in every language. */
    #[Test]
    public function the_roles_matrix_calls_the_section_by_its_name(): void
    {
        $this->signIn(['payments.view']);
        $title = fn (): string => collect($this->app->make(PermissionMatrix::class)->for(auth('cms')->user()))->firstWhere('module', 'payments')['title'];

        $this->assertSame('Payments', $title());
        $this->app->setLocale('ru');
        $this->assertSame('Оплата', $title());
    }

    #[Test]
    public function every_route_asks_for_a_declared_permission(): void
    {
        $this->assertPanelRoutesNeedPermission(['WebxUi\\Payments\\Http\\Panel\\', 'api/cms/payments/']);
    }

    #[Test]
    public function every_tool_asks_for_a_declared_permission(): void
    {
        $this->assertToolsNeedPermission('payments');
    }

    #[Test]
    public function reading_does_not_open_the_writes_nor_the_bodies(): void
    {
        $this->signIn(['payments.view']);

        $this->getJson($this->api('methods'))->assertOk();
        $this->getJson($this->api('attempts'))->assertOk();
        $this->getJson($this->api('exchange'))->assertOk();
        $this->getJson($this->api('groups'))->assertOk();
        $this->putJson($this->api('groups/online'), ['titles' => []])->assertForbidden();
        $this->putJson($this->api('methods/order'), ['ids' => []])->assertForbidden();
        $this->postJson($this->api('methods/1/test'))->assertForbidden();
        $this->putJson($this->api('methods/1'), ['values' => []])->assertForbidden();
        $this->get($this->api('exchange/1/request'))->assertForbidden();
        $this->postJson($this->api('attempts/1/check'))->assertForbidden();
    }

    /**
     * A right of another section opens nothing here: the attempts and the journal declare no
     * permissions of their own, and were shown in the menu to everybody signed in.
     */
    #[Test]
    public function another_sections_right_opens_nothing_of_the_payments(): void
    {
        $this->signIn(['delivery.view']);

        $this->assertSame(array_fill_keys(array_keys(PaymentsSection::SECTIONS), false), $this->entries());
        $this->getJson($this->api('methods'))->assertForbidden();
        $this->getJson($this->api('attempts'))->assertForbidden();
        $this->getJson($this->api('exchange'))->assertForbidden();
        $this->getJson($this->api('groups'))->assertForbidden();
        $this->putJson($this->api('groups/online'), ['titles' => []])->assertForbidden();
        $this->putJson($this->api('methods/order'), ['ids' => []])->assertForbidden();
    }

    #[Test]
    public function the_reader_sees_every_entry(): void
    {
        $this->signIn(['payments.view']);

        $this->assertSame(array_fill_keys(array_keys(PaymentsSection::SECTIONS), true), $this->entries());
    }

    #[Test]
    public function the_bodies_alone_show_no_entry(): void
    {
        $this->signIn(['payments.exchange']);

        $this->assertSame(array_fill_keys(array_keys(PaymentsSection::SECTIONS), false), $this->entries());
        $this->getJson($this->api('exchange'))->assertForbidden();
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
