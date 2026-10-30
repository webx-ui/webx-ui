<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Checkout;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\Events\CartsMerging;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Mcp\Registry\BoundTool;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Orders\Tests\Panel\PanelTestCase;
use WebxUi\Settings\Settings;
use WebxUi\Settings\SettingsServiceProvider;

/**
 * `webx-orders.checkout.enabled = false` (spec §19, decision 80): nothing of the checkout is wired
 * in — no prune command or its schedule, no listener of the cart's merge, no tab «Checkout», no
 * tools `orders_checkout_*` or `orders://checkout` —
 * while orders, their settings and `Orders::place()` stand as they were.
 */
final class CheckoutDisabledTest extends PanelTestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), SettingsServiceProvider::class, McpServiceProvider::class];
    }

    /** @param  Application  $app */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-orders.checkout.enabled', false);
    }

    #[Test]
    public function there_is_no_command_no_schedule_and_no_listener(): void
    {
        $this->assertArrayNotHasKey('webx:orders:checkout-prune', $this->app->make(Kernel::class)->all());

        $commands = array_map(static fn ($event): string => (string) $event->command, $this->app->make(Schedule::class)->events());
        $this->assertSame([], array_values(array_filter($commands, static fn (string $command): bool => str_contains($command, 'checkout-prune'))));

        $this->assertFalse(Event::hasListeners(CartsMerging::class));
    }

    #[Test]
    public function the_settings_have_no_tab_checkout_and_take_none_of_its_keys(): void
    {
        $this->signIn(['orders.statuses', 'orders.checkout.settings']);

        $root = $this->getJson('/api/cms/screens/orders.settings')->assertOk()->json('data.root');
        $tabs = array_column($root[0]['children'], 'id');
        $this->assertSame(['orders-tab'], $tabs);

        $values = $this->getJson($this->api('settings'))->assertOk()->json('data.values');
        $this->assertArrayNotHasKey('orders.checkout.terms', $values);

        $this->putJson($this->api('settings'), ['values' => ['orders.checkout.terms' => 'checkbox']])->assertOk();
        $this->assertArrayNotHasKey('orders.checkout.terms', $this->app->make(Settings::class)->raw());
    }

    #[Test]
    public function an_agent_has_no_tools_of_the_checkout(): void
    {
        $registry = $this->app->make(ToolRegistry::class);
        $names = array_map(static fn (BoundTool $tool): string => $tool->fullName(), $registry->toolsOf('orders'));

        $this->assertContains('orders_list', $names);
        $this->assertSame([], array_values(array_filter($names, static fn (string $name): bool => str_starts_with($name, 'orders_checkout_'))));
        $this->assertNotContains('orders://checkout', array_map(static fn (McpResource $resource): string => $resource->uri, $registry->resources()));
    }

    #[Test]
    public function an_order_still_comes_from_the_code(): void
    {
        $this->assertSame('1001', $this->order()->number);
    }
}
