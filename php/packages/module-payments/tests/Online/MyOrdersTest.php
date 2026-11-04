<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Online;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\OrdersServiceProvider;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\Testing\FakeGatewaySite;
use WebxUi\Payments\Types\OnReceipt;
use WebxUi\Users\Models\User;
use WebxUi\Users\UsersServiceProvider;

/**
 * «My orders» says what the order's page says (spec §8.4): an order the buyer can pay online reads
 * «Awaiting payment», not «Accepted»; paid, held and offline orders keep the shop's words.
 */
final class MyOrdersTest extends OnlineCase
{
    use CountsQueries;

    private User $buyer;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        $providers = parent::getPackageProviders($app);
        array_splice($providers, (int) array_search(OrdersServiceProvider::class, $providers, true), 0, [UsersServiceProvider::class]);

        return $providers;
    }

    protected function setUp(): void
    {
        parent::setUp();

        PaymentMethod::query()->where('key', OnReceipt::METHOD)->update(['enabled' => true]);
        $this->buyer = User::query()->create(['email' => 'buyer@example.test', 'password' => 'correct-horse-battery', 'first_name' => 'Buyer']);
        $this->actingAs($this->buyer, 'site');
    }

    #[Test]
    public function an_order_awaiting_an_online_payment_says_so_in_the_list(): void
    {
        $this->mine($this->onlineOrder());

        $this->get('/account/orders')
            ->assertOk()
            ->assertSee('Awaiting payment')
            ->assertSee('webx-account-orders__status--awaiting-payment', false)
            ->assertDontSee('Accepted');
    }

    #[Test]
    public function the_words_are_those_of_the_language_of_the_page(): void
    {
        $this->mine($this->onlineOrder());

        $this->get('/ru/account/orders')->assertOk()->assertSee('Ждёт оплаты');
    }

    #[Test]
    public function a_paid_order_is_back_to_the_public_status(): void
    {
        [$order, $payment] = $this->attemptAtGateway();
        $this->mine($order);
        $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $payment->uuid, 'action' => 'pay']));

        $this->get('/account/orders')->assertOk()->assertSee('Accepted')->assertDontSee('Awaiting payment');
    }

    #[Test]
    public function money_held_is_paid_for_the_buyer(): void
    {
        [$order, $payment] = $this->attemptAtGateway(hold: true);
        $this->mine($order);
        $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $payment->uuid, 'action' => 'pay']));

        $this->assertTrue($order->refresh()->awaiting_payment);
        $this->get('/account/orders')->assertOk()->assertSee('Accepted')->assertDontSee('Awaiting payment');
    }

    #[Test]
    public function an_order_paid_offline_keeps_the_shops_words(): void
    {
        $this->mine($this->place($this->cart(), [
            'awaitPayment' => true,
            'blocks' => ['payment' => [
                'method' => 'bank-transfer',
                'type' => 'bank-transfer',
                'kind' => 'offline',
                'title' => ['en' => 'Bank transfer'],
                'price' => null,
            ]],
        ]));

        $this->get('/account/orders')->assertOk()->assertSee('Accepted')->assertDontSee('Awaiting payment');
    }

    #[Test]
    public function a_cancelled_order_awaits_nothing(): void
    {
        $order = $this->onlineOrder();
        $this->mine($order);
        $order->forceFill(['cancelled_at' => now()])->save();

        $this->get('/account/orders')->assertOk()->assertDontSee('Awaiting payment');
    }

    #[Test]
    public function the_list_asks_about_payments_once_whatever_its_length(): void
    {
        foreach (range(1, 3) as $ignored) {
            $this->mine($this->onlineOrder());
        }

        $this->assertNoRepeatedQueries(fn () => $this->get('/account/orders')->assertOk()->assertSee('Awaiting payment'));
    }

    private function mine(Order $order): void
    {
        $order->forceFill(['user_id' => $this->buyer->id])->save();
    }
}
