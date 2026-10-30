<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Site;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Cart\Money;
use WebxUi\Orders\ItemsEdit;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderItem;
use WebxUi\Orders\OrderDetails;
use WebxUi\Orders\Orders;
use WebxUi\Orders\Site\OrderLinks;
use WebxUi\Orders\Tests\PlaceTestCase;
use WebxUi\Themes\ThemeServiceProvider;

/**
 * The buyer's page behind the signed link (spec §13.1) on a site without the visitors module:
 * the link is the key, a wrong one is a 404, the page says what the buyer may know and nothing
 * the shop keeps for itself — and «My orders» and the linking are nowhere (§13.2, §13.3).
 */
final class OrderPageTest extends PlaceTestCase
{
    use CountsQueries;

    /**
     * The theme engine first: the widgets the page stands on want it.
     *
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ThemeServiceProvider::class, ...parent::getPackageProviders($app)];
    }

    private function link(Order $order, string $locale = 'en'): string
    {
        return $this->app->make(OrderLinks::class)->page((string) $order->number, $locale);
    }

    #[Test]
    public function the_signed_link_opens_the_order_as_the_buyer_may_see_it(): void
    {
        $order = $this->place($this->cart([['type' => 'test.product', 'ref' => 1, 'qty' => 2]]));
        $this->app->make(Orders::class)->updateDetails($order, (new OrderDetails)->managerNote('Calls after six only'), 'panel');

        $page = $this->get($this->link($order))->assertOk();

        $this->assertStringContainsString('no-store', (string) $page->headers->get('Cache-Control'));
        $this->assertSame('noindex', $page->headers->get('X-Robots-Tag'));
        // The signature is in the address: it must not leave with a click on a picture's host.
        $this->assertSame('no-referrer', $page->headers->get('Referrer-Policy'));
        $page->assertSee('Order '.$order->number)
            ->assertSee('Item A')
            ->assertSee('Accepted')
            ->assertSee('We have your order and will be in touch.')
            ->assertSee('In progress')
            ->assertSee('alex@example.test')
            // The manager's note and the internal status are the shop's.
            ->assertDontSee('Calls after six only')
            ->assertDontSee('Taken into work')
            // «Cancelled» is no step ahead of an order that is not.
            ->assertDontSee('Cancelled');
    }

    #[Test]
    public function a_cancelled_order_says_so_and_has_no_road_ahead(): void
    {
        $order = $this->place($this->cart());
        $this->app->make(Orders::class)->mark($order, Milestone::Paid, 'panel');
        $this->app->make(Orders::class)->cancel($order, 'panel', 'Out of stock');

        $this->get($this->link($order))->assertOk()
            ->assertSee('Paid')
            ->assertSee('Cancelled')
            ->assertDontSee('Out of stock');
    }

    #[Test]
    public function an_edited_order_says_when_and_what_is_left_to_pay_or_comes_back(): void
    {
        $orders = $this->app->make(Orders::class);
        $order = $this->place($this->cart([['type' => 'test.product', 'ref' => 1], ['type' => 'test.product', 'ref' => 3]]));

        // Untouched and not paid: no mark, and «to pay» says it all.
        $this->get($this->link($order))->assertOk()
            ->assertDontSee('Items changed')
            ->assertDontSee('Left to pay');

        $orders->mark($order, Milestone::Paid, 'panel');
        [$a, $c] = OrderItem::query()->where('order_id', $order->id)->orderBy('id')->get()->all();

        // Up: one more Item A, Part C taken out (§40.1).
        $orders->editItems($order->refresh(), new ItemsEdit(revision: 0, qty: [$a->id => 2], remove: [$c->id], reason: 'Warehouse mix-up', message: 'One more, as you asked.'));

        $this->get($this->link($order))->assertOk()
            ->assertSee('Items changed on '.$order->refresh()->items_changed_at?->locale('en')->isoFormat('LL'))
            ->assertSee('Left to pay: '.$this->app->make(Money::class)->format(4299, 'EUR'))
            ->assertSee('Item A')
            ->assertDontSee('Part C')
            ->assertDontSee('Warehouse mix-up')
            ->assertDontSee('One more, as you asked.');

        // Down below what was paid: the shop owes the buyer.
        $orders->editItems($order->refresh(), new ItemsEdit(revision: 1, qty: [$a->id => 1]));

        $this->get($this->link($order))->assertOk()
            ->assertSee('We will give you back '.$this->app->make(Money::class)->format(700, 'EUR'))
            ->assertDontSee('Left to pay');
    }

    #[Test]
    public function a_wrong_or_missing_signature_is_a_page_that_does_not_exist(): void
    {
        $order = $this->place($this->cart());
        $url = $this->link($order);

        $this->get(preg_replace('/signature=[0-9a-f]{8}/', 'signature=00000000', $url))->assertNotFound();
        $this->get('/order/'.$order->number)->assertNotFound();
        // Another number under the same signature is not the order it was made for.
        $this->get(str_replace('/order/'.$order->number, '/order/'.$order->number.'0', $url))->assertNotFound();
    }

    #[Test]
    public function the_link_carries_the_language_of_the_order(): void
    {
        $order = $this->place($this->cart(), ['locale' => 'ru']);
        $url = $this->link($order, 'ru');

        $this->assertStringStartsWith('http://localhost/ru/order/'.$order->number.'?signature=', $url);
        $this->get($url)->assertOk()->assertSee('Заказ '.$order->number)->assertSee('Товар A')->assertSee('Принят');
        // The prefix is signed with the path: the same link in another language is not one.
        $this->get(str_replace('/ru/', '/kk/', $url))->assertNotFound();
    }

    #[Test]
    public function the_page_costs_the_order_and_not_its_statuses_once_they_are_cached(): void
    {
        $this->app['config']->set('webx-cache.enabled', true);
        $order = $this->place($this->cart([['type' => 'test.product', 'ref' => 1], ['type' => 'test.product', 'ref' => 3]]));
        $url = $this->link($order);
        $this->get($url)->assertOk();

        // The order, its lines, adjustments and tenders: the statuses come from the cache.
        $this->assertQueriesWithin(4, fn () => $this->get($url)->assertOk());
        $this->assertNoRepeatedQueries(fn () => $this->get($url)->assertOk());
    }

    #[Test]
    public function without_the_visitors_module_there_is_no_account_and_no_linking(): void
    {
        $this->assertTrue(Route::has(OrderLinks::PAGE));
        $this->assertFalse(Route::has(OrderLinks::ACCOUNT));
        $this->assertFalse(Route::has('webx.orders.panel.visitor.link'));
        $this->assertFalse(Route::has('webx.orders.panel.visitor.search'));
        $this->assertFalse($this->app->make(ScreenRegistry::class)->has('users.form'));
    }
}
