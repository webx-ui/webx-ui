<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Checkout;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Cart\CartServiceProvider;
use WebxUi\Compositions\CompositionsServiceProvider;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Notifications\NotificationsServiceProvider;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\OrdersServiceProvider;
use WebxUi\Routing\RoutingServiceProvider;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * «Thank you» with the notifications installed (spec §26, decision 81): the set a site starts with
 * writes to the buyer on `orders.placed`, so the page says where the confirmation went — and stops
 * saying it once that channel is switched off.
 */
final class ThanksWithNotificationsTest extends CheckoutTestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ThemeServiceProvider::class,
            LocalizationServiceProvider::class,
            NestedSetServiceProvider::class,
            RoutingServiceProvider::class,
            CompositionsServiceProvider::class,
            AdminServiceProvider::class,
            AuthServiceProvider::class,
            McpServiceProvider::class,
            SettingsServiceProvider::class,
            NotificationsServiceProvider::class,
            WidgetsServiceProvider::class,
            CartServiceProvider::class,
            OrdersServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
    }

    #[Test]
    public function thank_you_says_where_the_confirmation_went(): void
    {
        $cart = $this->cart();
        $this->fill($cart, 'buyer-a@example.test');
        $this->submit($cart)->assertRedirect();
        $order = Order::query()->sole();

        $this->visit($cart, '/checkout/thanks/'.$order->number)->assertOk()
            ->assertSee('We sent a confirmation to buyer-a@example.test.');

        Channel::query()->where('event', 'orders.placed')->update(['enabled' => false]);

        $this->visit($cart, '/checkout/thanks/'.$order->number)->assertOk()
            ->assertDontSee('We sent a confirmation');
    }
}
